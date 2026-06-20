package usecase

import (
	"errors"
	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"

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

	// 3. Ambil data Room dan Guest dari external service
	extRoom, errRoom := infrastructure.FetchRoomFromCatalog(booking.RoomID.String())
	extGuest, errGuest := infrastructure.FetchGuestFromGuestService(booking.GuestID.String())

	var room *domain.Room
	if errRoom == nil && extRoom != nil {
		roomID, _ := uuid.Parse(extRoom.ID)
		room = &domain.Room{
			ID:            roomID,
			Name:          extRoom.Name,
			Location:      extRoom.Location,
			Description:   extRoom.Description,
			PricePerNight: extRoom.PricePerNight,
			Status:        extRoom.Status,
		}
	} else {
		// Fallback empty room jika gagal menghubungi service
		roomID, _ := uuid.Parse(booking.RoomID.String())
		room = &domain.Room{ID: roomID, Name: "Unknown Room"}
	}

	var guest *domain.Guest
	if errGuest == nil && extGuest != nil {
		guestID, _ := uuid.Parse(extGuest.ID)
		guest = &domain.Guest{
			ID:          guestID,
			Name:        extGuest.Name,
			Email:       extGuest.Email,
			KtpNumber:   extGuest.KtpNumber,
			PhoneNumber: extGuest.PhoneNumber,
		}
	} else {
		// Fallback empty guest jika gagal menghubungi service
		guestID, _ := uuid.Parse(booking.GuestID.String())
		guest = &domain.Guest{ID: guestID, Name: "Unknown Guest"}
	}

	return &domain.BookingSummary{
		Booking:      booking,
		RoomDetails:  room,
		GuestDetails: guest,
	}, nil
}
