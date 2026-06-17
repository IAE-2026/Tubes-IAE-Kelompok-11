package usecase

import (
	"errors"
	"reservasi/internal/domain"

	"github.com/google/uuid"
)

func (u *bookingUsecase) AddAddon(bookingID string, req *domain.CreateBookingAddonRequest) (*domain.BookingAddon, error) {
	// 1. Validasi UUID
	bID, err := uuid.Parse(bookingID)
	if err != nil {
		return nil, errors.New("format booking_id tidak valid")
	}

	addonID, err := uuid.Parse(req.AddonID)
	if err != nil {
		return nil, errors.New("format addon_id tidak valid")
	}

	// 2. Ambil data Booking
	booking, err := u.bookingRepo.GetBookingByID(bookingID)
	if err != nil {
		return nil, errors.New("pesanan tidak ditemukan")
	}

	// 3. Ambil data Addon untuk cek harga asli
	addon, err := u.bookingRepo.GetAddonByID(req.AddonID)
	if err != nil {
		return nil, errors.New("layanan tambahan tidak ditemukan")
	}

	// 4. Buat Record Booking Addon
	bookingAddon := &domain.BookingAddon{
		BookingID:      bID,
		AddonID:        addonID,
		Quantity:       req.Quantity,
		PriceAtBooking: addon.Price, // Snapshot harga saat dibooking
	}

	if err := u.bookingRepo.CreateBookingAddon(bookingAddon); err != nil {
		return nil, errors.New("gagal menambahkan layanan ke pesanan")
	}

	// 5. Update Total Harga pada tabel Booking
	totalAddonPrice := addon.Price * float64(req.Quantity)
	booking.TotalAddonsPrice += totalAddonPrice
	booking.GrandTotal = booking.TotalRoomPrice + booking.TotalAddonsPrice
	
	if err := u.bookingRepo.UpdateBooking(booking); err != nil {
		return nil, errors.New("gagal memperbarui total harga pesanan")
	}

	return bookingAddon, nil
}
