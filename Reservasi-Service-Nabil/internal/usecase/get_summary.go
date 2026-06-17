package usecase

import (
	"errors"
	"reservasi/internal/domain"

	"github.com/google/uuid"
)

func (u *bookingUsecase) GetSummary(bookingID string) (*domain.BookingSummary, error) {
	// 1. Validasi UUID format
	if _, err := uuid.Parse(bookingID); err != nil {
		return nil, errors.New("format booking_id tidak valid")
	}

	// 2. Ambil Booking (berikut Addons nya melalui Preload repo)
	booking, err := u.bookingRepo.GetBookingByID(bookingID)
	if err != nil {
		return nil, err
	}

	// 3. Ambil data Room dan Guest secara asinkron atau sinkron (disini sinkron)
	room, _ := u.bookingRepo.GetRoomByID(booking.RoomID.String())
	guest, _ := u.bookingRepo.GetGuestByID(booking.GuestID.String())

	return &domain.BookingSummary{
		Booking:      booking,
		RoomDetails:  room,
		GuestDetails: guest,
	}, nil
}
