package usecase

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"math"
	"time"

	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"

	"github.com/google/uuid"
)

func (u *bookingUsecase) CreateBooking(req *domain.CreateBookingRequest) (*domain.Booking, error) {
	// =========================================================================
	// STEP 1: Validasi Format UUID
	// =========================================================================
	guestID, err := uuid.Parse(req.GuestID)
	if err != nil {
		return nil, errors.New("format guest_id tidak valid")
	}

	roomID, err := uuid.Parse(req.RoomID)
	if err != nil {
		return nil, errors.New("format room_id tidak valid")
	}

	// =========================================================================
	// STEP 2: Parse Tanggal (Format: YYYY-MM-DD)
	// =========================================================================
	checkIn, err := time.Parse("2006-01-02", req.CheckInDate)
	if err != nil {
		return nil, errors.New("format check_in_date tidak valid (gunakan YYYY-MM-DD)")
	}

	checkOut, err := time.Parse("2006-01-02", req.CheckOutDate)
	if err != nil {
		return nil, errors.New("format check_out_date tidak valid (gunakan YYYY-MM-DD)")
	}

	if !checkOut.After(checkIn) {
		return nil, errors.New("check_out_date harus setelah check_in_date")
	}

	// =========================================================================
	// STEP 3 [INTER-SERVICE]: Validasi Tamu via Guest Service
	// Memanggil http://guest-service:8000/internal/{guestId}
	// Header X-INTERNAL-KEY disuntikkan secara otomatis oleh FetchGuestFromGuestService.
	// Jika tamu tidak ditemukan atau service tidak dapat dijangkau → return error 400.
	// =========================================================================
	guestData, err := infrastructure.FetchGuestFromGuestService(req.GuestID)
	if err != nil {
		log.Printf("[CreateBooking] Gagal validasi tamu dari Guest Service: %v", err)
		return nil, fmt.Errorf("validasi tamu gagal: %v", err)
	}
	log.Printf("[CreateBooking] Tamu tervalidasi dari Guest Service: %s (%s)", guestData.Name, guestData.Email)

	// =========================================================================
	// STEP 4 [INTER-SERVICE]: Validasi & Ambil Detail Kamar via Catalog Service
	// Memanggil http://catalog-service:8000/internal/rooms/{roomId}
	// Header X-INTERNAL-KEY disuntikkan secara otomatis oleh FetchRoomFromCatalog.
	// Jika kamar tidak ditemukan atau tidak tersedia → return error 400.
	// =========================================================================
	roomData, err := infrastructure.FetchRoomFromCatalog(req.RoomID)
	if err != nil {
		log.Printf("[CreateBooking] Gagal validasi kamar dari Catalog Service: %v", err)
		return nil, fmt.Errorf("validasi kamar gagal: %v", err)
	}
	log.Printf("[CreateBooking] Kamar tervalidasi dari Catalog Service: %s (Rp %.2f/malam)", roomData.Name, roomData.PricePerNight)

	// Validasi status kamar dari Catalog Service
	if roomData.Status != "available" && roomData.Status != "AVAILABLE" {
		return nil, fmt.Errorf("kamar '%s' tidak tersedia untuk dipesan (status: %s)", roomData.Name, roomData.Status)
	}

	// =========================================================================
	// STEP 5: Validasi Idempotency Key (Cegah request duplikat)
	// =========================================================================
	ctx := context.Background()
	if req.IdempotencyKey != "" {
		idempotencyRedisKey := "idempotency:" + req.IdempotencyKey
		if infrastructure.RedisClient != nil {
			ok, err := infrastructure.RedisClient.SetNX(ctx, idempotencyRedisKey, "1", 86400*time.Second).Result()
			if err != nil {
				log.Printf("Warning: Gagal cek idempotency di Redis: %v", err)
			} else if !ok {
				return nil, errors.New("request duplikat terdeteksi (Idempotency-Key sudah pernah digunakan)")
			}
		}
	}

	// =========================================================================
	// STEP 6: Verifikasi Redis Hold Lock
	// Kamar harus sudah di-hold oleh guest yang sama sebelum booking dikonfirmasi.
	// =========================================================================
	heldBy, err := u.bookingRepo.GetRoomHold(ctx, req.RoomID)
	if err != nil {
		return nil, errors.New("gagal mengecek status hold kamar")
	}
	if heldBy == "" {
		return nil, errors.New("sesi pemesanan anda telah habis, silakan mulai ulang dengan hold kamar terlebih dahulu")
	}
	if heldBy != req.GuestID {
		return nil, errors.New("kamar ini sedang ditahan oleh pengguna lain")
	}

	// =========================================================================
	// STEP 7: Hitung Harga (menggunakan harga dari Catalog Service)
	// =========================================================================
	duration := checkOut.Sub(checkIn)
	nights := int(math.Ceil(duration.Hours() / 24))
	if nights < 1 {
		nights = 1
	}

	// Harga per malam diambil dari Catalog Service (bukan dari DB lokal)
	totalRoomPrice := roomData.PricePerNight * float64(nights)
	expiresAt := time.Now().Add(1 * time.Hour)

	// =========================================================================
	// STEP 8: INSERT Booking ke Database Lokal (PostgreSQL)
	// =========================================================================
	booking := &domain.Booking{
		GuestID:          guestID,
		RoomID:           roomID,
		CheckInDate:      checkIn,
		CheckOutDate:     checkOut,
		TotalRoomPrice:   totalRoomPrice,
		TotalAddonsPrice: 0,
		GrandTotal:       totalRoomPrice,
		Status:           "LOCKED",
		ExpiresAt:        &expiresAt,
	}

	if err := u.bookingRepo.CreateBooking(booking); err != nil {
		return nil, errors.New("gagal membuat pesanan: " + err.Error())
	}
	log.Printf("[CreateBooking] Booking berhasil dibuat: ID=%s, Total=Rp %.2f", booking.ID.String(), booking.GrandTotal)

	// =========================================================================
	// STEP 9 [CLOUD]: SOAP Audit Logging
	// Kirim data booking ke SOAP server Cloud Pusat untuk mendapatkan receipt number.
	// Jika gagal, masukkan ke retry queue (Redis List) dan lanjutkan tanpa error fatal.
	// =========================================================================
	payloadBytes, _ := json.Marshal(booking)
	receiptStr, err := infrastructure.SendAuditLog(ctx, string(payloadBytes))
	if err != nil {
		log.Printf("[CreateBooking] Warning: Gagal mengirim audit log SOAP: %v", err)
		if infrastructure.RedisClient != nil {
			infrastructure.RedisClient.LPush(ctx, "retry:soap", string(payloadBytes))
		}
	} else if receiptStr != "" {
		booking.ReceiptNumber = &receiptStr
		if err := u.bookingRepo.UpdateBooking(booking); err != nil {
			log.Printf("[CreateBooking] Warning: Gagal update receipt number di database: %v", err)
		}
		log.Printf("[CreateBooking] SOAP Audit sukses. Receipt Number: %s", receiptStr)
	}

	// =========================================================================
	// STEP 10 [CLOUD]: RabbitMQ Broadcast Event
	// Publish event booking.created ke RabbitMQ Cloud Pusat.
	// Payload diperkaya dengan data tamu & kamar dari inter-service call.
	// Jika gagal, masukkan ke retry queue dan lanjutkan.
	// =========================================================================
	receiptNumber := ""
	if booking.ReceiptNumber != nil {
		receiptNumber = *booking.ReceiptNumber
	}

	event := infrastructure.BookingEvent{
		BookingID:     booking.ID.String(),
		Status:        booking.Status,
		Timestamp:     time.Now(),
		// Enrichment dari inter-service call
		GuestName:     guestData.Name,
		GuestEmail:    guestData.Email,
		RoomName:      roomData.Name,
		RoomPrice:     roomData.PricePerNight,
		ReceiptNumber: receiptNumber,
	}

	if err := infrastructure.PublishBookingEvent(ctx, event); err != nil {
		log.Printf("[CreateBooking] Warning: Gagal publish event RabbitMQ: %v", err)
		if infrastructure.RedisClient != nil {
			eventBytes, _ := json.Marshal(event)
			infrastructure.RedisClient.LPush(ctx, "retry:rabbitmq", string(eventBytes))
		}
	} else {
		log.Printf("[CreateBooking] RabbitMQ broadcast sukses untuk Booking ID: %s", booking.ID.String())
	}

	// =========================================================================
	// STEP 11: Lepas Hold Redis
	// =========================================================================
	_ = u.bookingRepo.ReleaseRoom(context.Background(), req.RoomID, req.GuestID)

	return booking, nil
}
