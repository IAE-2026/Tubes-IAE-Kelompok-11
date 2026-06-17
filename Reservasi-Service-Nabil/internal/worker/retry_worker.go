package worker

import (
	"context"
	"encoding/json"
	"log"
	"time"

	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"
)

// StartRetryWorker menjalankan goroutine background untuk retry pengiriman payload yang gagal.
// Membaca Redis List (retry:soap dan retry:rabbitmq) secara berkala.
func StartRetryWorker(repo domain.BookingRepository) {
	// Gunakan durasi yang relatif aman (misal 60 detik) untuk pooling
	ticker := time.NewTicker(60 * time.Second)

	go func() {
		log.Println("[Retry Worker] Background worker dimulai. Memeriksa antrean setiap 60 detik...")
		for range ticker.C {
			if infrastructure.RedisClient == nil {
				continue
			}

			processSOAPRetry(repo)
			processRabbitMQRetry()
		}
	}()
}

func processSOAPRetry(repo domain.BookingRepository) {
	ctx := context.Background()

	// Proses maksimal 10 pesan per siklus untuk mencegah blocking terlalu lama
	for i := 0; i < 10; i++ {
		// Gunakan RPOP untuk mengambil pesan paling lama dari antrean (FIFO)
		payloadJSON, err := infrastructure.RedisClient.RPop(ctx, "retry:soap").Result()
		if err != nil || payloadJSON == "" {
			break // Antrean kosong atau error Redis
		}

		log.Printf("[Retry Worker] Memproses ulang SOAP audit...")
		
		// Unmarshal payload untuk mengambil BookingID
		var booking domain.Booking
		if err := json.Unmarshal([]byte(payloadJSON), &booking); err != nil {
			log.Printf("[Retry Worker] Gagal unmarshal payload SOAP yang rusak: %v", err)
			continue // Abaikan data rusak dan lanjutkan ke data selanjutnya
		}

		// Coba kirim ulang ke infrastruktur eksternal
		receiptStr, err := infrastructure.SendAuditLog(ctx, payloadJSON)
		if err != nil {
			log.Printf("[Retry Worker] SOAP audit (Retry) masih gagal: %v. Mengembalikan ke antrean.", err)
			// Kembalikan ke depan antrean (LPUSH) karena gagal
			infrastructure.RedisClient.LPush(ctx, "retry:soap", payloadJSON)
			break // Berhenti memproses SOAP di siklus ini, coba lagi nanti
		}

		// Jika berhasil, perbarui nilai ReceiptNumber pada Booking yang ada di PostgreSQL
		if receiptStr != "" {
			booking.ReceiptNumber = &receiptStr
			if err := repo.UpdateBooking(&booking); err != nil {
				log.Printf("[Retry Worker] SOAP sukses, namun gagal update resi di database: %v", err)
			} else {
				log.Printf("[Retry Worker] SOAP audit (Retry) sukses. Receipt Number: %s", receiptStr)
			}
		}
	}
}

func processRabbitMQRetry() {
	ctx := context.Background()

	for i := 0; i < 10; i++ {
		eventJSON, err := infrastructure.RedisClient.RPop(ctx, "retry:rabbitmq").Result()
		if err != nil || eventJSON == "" {
			break
		}

		log.Printf("[Retry Worker] Memproses ulang RabbitMQ event...")

		var event infrastructure.BookingEvent
		if err := json.Unmarshal([]byte(eventJSON), &event); err != nil {
			log.Printf("[Retry Worker] Gagal unmarshal event RabbitMQ yang rusak: %v", err)
			continue
		}

		if err := infrastructure.PublishBookingEvent(ctx, event); err != nil {
			log.Printf("[Retry Worker] RabbitMQ publish (Retry) masih gagal: %v. Mengembalikan ke antrean.", err)
			infrastructure.RedisClient.LPush(ctx, "retry:rabbitmq", eventJSON)
			break
		}

		log.Printf("[Retry Worker] RabbitMQ publish (Retry) sukses.")
	}
}
