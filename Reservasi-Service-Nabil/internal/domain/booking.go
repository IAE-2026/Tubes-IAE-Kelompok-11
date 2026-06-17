package domain

import (
	"context"
	"time"

	"github.com/google/uuid"
)

type Booking struct {
	ID               uuid.UUID      `gorm:"type:uuid;primaryKey;default:uuid_generate_v4()" json:"id"`
	GuestID          uuid.UUID      `gorm:"type:uuid" json:"guest_id"`
	RoomID           uuid.UUID      `gorm:"type:uuid" json:"room_id"`
	CheckInDate      time.Time      `gorm:"type:date;not null" json:"check_in_date"`
	CheckOutDate     time.Time      `gorm:"type:date;not null" json:"check_out_date"`
	TotalRoomPrice   float64        `gorm:"type:decimal(12,2);not null" json:"total_room_price"`
	TotalAddonsPrice float64        `gorm:"type:decimal(12,2);default:0" json:"total_addons_price"`
	GrandTotal       float64        `gorm:"type:decimal(12,2);default:0" json:"grand_total"`
	Status           string         `gorm:"type:varchar(50);default:'LOCKED'" json:"status"`
	CreatedAt        time.Time      `gorm:"autoCreateTime" json:"created_at"`
	ExpiresAt        *time.Time     `json:"expires_at"`
	ReceiptNumber    *string        `gorm:"type:varchar(100)" json:"receipt_number"`
	Addons           []BookingAddon `gorm:"foreignKey:BookingID" json:"addons,omitempty"`
}

type BookingAddon struct {
	ID             uuid.UUID `gorm:"type:uuid;primaryKey;default:uuid_generate_v4()" json:"id"`
	BookingID      uuid.UUID `gorm:"type:uuid;onDelete:CASCADE" json:"booking_id"`
	AddonID        uuid.UUID `gorm:"type:uuid" json:"addon_id"`
	Quantity       int       `gorm:"default:1" json:"quantity"`
	PriceAtBooking float64   `gorm:"type:decimal(12,2);not null" json:"price_at_booking"`
}

// Data Request
type CreateBookingRequest struct {
	GuestID        string `json:"guest_id" binding:"required,uuid"`
	RoomID         string `json:"room_id" binding:"required,uuid"`
	CheckInDate    string `json:"check_in_date" binding:"required"`  // format YYYY-MM-DD
	CheckOutDate   string `json:"check_out_date" binding:"required"` // format YYYY-MM-DD
	IdempotencyKey string `json:"-"`                                 // Diisi dari header, bukan JSON body
}

type CreateBookingAddonRequest struct {
	AddonID  string `json:"addon_id" binding:"required,uuid"`
	Quantity int    `json:"quantity" binding:"required,min=1"`
}

type HoldRoomRequest struct {
	GuestID string `json:"guest_id" binding:"required,uuid"`
}

// Data Response untuk Summary
type BookingSummary struct {
	Booking      *Booking `json:"booking"`
	RoomDetails  *Room    `json:"room_details"`
	GuestDetails *Guest   `json:"guest_details"`
}

// BookingRepository mengatur interaksi dengan database
type BookingRepository interface {
	CreateBooking(booking *Booking) error
	CreateBookingAddon(addon *BookingAddon) error
	GetBookingByID(id string) (*Booking, error)
	UpdateBooking(booking *Booking) error
	UpdateBookingStatus(bookingID string, status string) error
	UpdateRoomStatus(roomID string, status string) error
	GetRoomByID(id string) (*Room, error)
	GetAddonByID(id string) (*Addon, error)
	GetGuestByID(id string) (*Guest, error)
	
	// Redis temporary hold
	HoldRoom(ctx context.Context, roomID string, guestID string, ttl time.Duration) error
	ReleaseRoom(ctx context.Context, roomID string, guestID string) error
	GetRoomHold(ctx context.Context, roomID string) (string, error)
}

// BookingUsecase mengatur logika bisnis aplikasi
type BookingUsecase interface {
	CreateBooking(req *CreateBookingRequest) (*Booking, error)
	AddAddon(bookingID string, req *CreateBookingAddonRequest) (*BookingAddon, error)
	GetSummary(bookingID string) (*BookingSummary, error)

	HoldRoom(roomID string, req *HoldRoomRequest) error
	ReleaseRoom(roomID string, guestID string) error
}
