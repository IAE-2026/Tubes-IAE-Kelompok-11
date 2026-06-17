package usecase

import (
	"reservasi/internal/domain"
)

type bookingUsecase struct {
	bookingRepo domain.BookingRepository
}

// NewBookingUsecase membuat instance usecase baru
func NewBookingUsecase(bookingRepo domain.BookingRepository) domain.BookingUsecase {
	return &bookingUsecase{bookingRepo: bookingRepo}
}
