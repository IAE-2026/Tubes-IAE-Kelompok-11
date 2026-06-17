package usecase

import (
	"context"
	"errors"
	"reservasi/internal/domain"
	"time"

	"github.com/google/uuid"
)

func (u *bookingUsecase) HoldRoom(roomID string, req *domain.HoldRoomRequest) error {
	ctx := context.Background()

	// Validasi UUID
	if _, err := uuid.Parse(roomID); err != nil {
		return errors.New("format room_id tidak valid")
	}
	if _, err := uuid.Parse(req.GuestID); err != nil {
		return errors.New("format guest_id tidak valid")
	}

	// Pastikan room dan guest terdaftar di database
	if _, err := u.bookingRepo.GetRoomByID(roomID); err != nil {
		return errors.New("kamar tidak ditemukan")
	}
	if _, err := u.bookingRepo.GetGuestByID(req.GuestID); err != nil {
		return errors.New("tamu tidak ditemukan")
	}

	// Durasi hold adalah 10 menit
	ttl := 10 * time.Minute

	return u.bookingRepo.HoldRoom(ctx, roomID, req.GuestID, ttl)
}

func (u *bookingUsecase) ReleaseRoom(roomID string, guestID string) error {
	ctx := context.Background()

	// Validasi UUID
	if _, err := uuid.Parse(roomID); err != nil {
		return errors.New("format room_id tidak valid")
	}
	if _, err := uuid.Parse(guestID); err != nil {
		return errors.New("format guest_id tidak valid")
	}

	return u.bookingRepo.ReleaseRoom(ctx, roomID, guestID)
}
