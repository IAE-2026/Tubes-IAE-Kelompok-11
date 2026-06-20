package usecase

import (
	"context"
	"errors"
	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"
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

	// Pastikan room dan guest terdaftar dengan memanggil service lain (Arsitektur Microservice)
	if _, err := infrastructure.FetchRoomFromCatalog(roomID); err != nil {
		return err
	}
	if _, err := infrastructure.FetchGuestFromGuestService(req.GuestID); err != nil {
		return err
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
