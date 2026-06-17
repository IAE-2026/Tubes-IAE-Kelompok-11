package usecase

import (
	"context"
	"encoding/json"
	"errors"
	"log"
	"math"
	"time"

	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"

	"github.com/google/uuid"
)

func (u *bookingUsecase) CreateBooking(req *domain.CreateBookingRequest) (*domain.Booking, error) {
	// 1. Validasi Format UUID
	guestID, err := uuid.Parse(req.GuestID)
	if err != nil {
		return nil, errors.New("format guest_id tidak valid")
	}

	roomID, err := uuid.Parse(req.RoomID)
	if err != nil {
		return nil, errors.New("format room_id tidak valid")
	}

	// 2. Parse Tanggal (Format: YYYY-MM-DD)
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

	// 3. Ambil data Room untuk mendapatkan harga per malam
	room, err := u.bookingRepo.GetRoomByID(req.RoomID)
	if err != nil {
		return nil, errors.New("kamar tidak ditemukan")
	}

	// 3b. VALIDASI IDEMPOTENCY KEY
	// Mencegah duplikasi request (sesuai sequence.md langkah #8)
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

	// 3c. VERIFIKASI REDIS LOCK
	heldBy, err := u.bookingRepo.GetRoomHold(ctx, req.RoomID)
	if err != nil {
		return nil, errors.New("gagal mengecek status kamar")
	}
	if heldBy == "" {
		return nil, errors.New("sesi pemesanan anda telah habis, silakan mulai ulang")
	}
	if heldBy != req.GuestID {
		return nil, errors.New("kamar ini sedang ditahan oleh pengguna lain")
	}

	// 4. Pastikan Guest terdaftar
	_, err = u.bookingRepo.GetGuestByID(req.GuestID)
	if err != nil {
		return nil, errors.New("tamu tidak ditemukan")
	}

	// 5. Hitung Durasi (Jumlah Malam)
	duration := checkOut.Sub(checkIn)
	nights := int(math.Ceil(duration.Hours() / 24))
	if nights < 1 {
		nights = 1 // Minimal 1 malam
	}

	// 6. Hitung Harga Total Kamar
	totalRoomPrice := room.PricePerNight * float64(nights)
	expiresAt := time.Now().Add(1 * time.Hour) // Diberi waktu 1 jam untuk bayar

	if room.Status != "AVAILABLE" {
		return nil, errors.New("kamar tidak tersedia")
	}

	if err := u.bookingRepo.UpdateRoomStatus(req.RoomID, "LOCKED"); err != nil {
		return nil, errors.New("gagal mengunci kamar di database")
	}

	// 7. Simpan Booking
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
		_ = u.bookingRepo.UpdateRoomStatus(req.RoomID, "AVAILABLE")
		return nil, errors.New("gagal membuat pesanan: " + err.Error())
	}

	// 8. SOAP Audit Logging
	payloadBytes, _ := json.Marshal(booking)
	receiptStr, err := infrastructure.SendAuditLog(ctx, string(payloadBytes))
	if err != nil {
		log.Printf("Warning: Gagal mengirim audit log SOAP: %v", err)
		if infrastructure.RedisClient != nil {
			infrastructure.RedisClient.LPush(ctx, "retry:soap", string(payloadBytes))
		}
	} else if receiptStr != "" {
		// 9. Update Receipt Number
		booking.ReceiptNumber = &receiptStr
		if err := u.bookingRepo.UpdateBooking(booking); err != nil {
			log.Printf("Warning: Gagal update receipt number di database: %v", err)
		}
	}

	// 10. Message Broker Broadcast
	event := infrastructure.BookingEvent{
		BookingID: booking.ID.String(),
		Status:    booking.Status,
		Timestamp: time.Now(),
	}
	if err := infrastructure.PublishBookingEvent(ctx, event); err != nil {
		log.Printf("Warning: Gagal publish event RabbitMQ: %v", err)
		if infrastructure.RedisClient != nil {
			eventBytes, _ := json.Marshal(event)
			infrastructure.RedisClient.LPush(ctx, "retry:rabbitmq", string(eventBytes))
		}
	}

	// Lepas sementara hold Redis agar tidak mengunci resource berlebih
	_ = u.bookingRepo.ReleaseRoom(context.Background(), req.RoomID, req.GuestID)

	return booking, nil
}
