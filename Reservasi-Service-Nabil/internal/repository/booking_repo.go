package repository

import (
	"errors"
	"reservasi/internal/domain"

	"context"
	"time"

	"github.com/redis/go-redis/v9"
	"gorm.io/gorm"
)

type bookingRepository struct {
	db    *gorm.DB
	redis *redis.Client
}

// NewBookingRepository membuat instance repository baru
func NewBookingRepository(db *gorm.DB, redisClient *redis.Client) domain.BookingRepository {
	return &bookingRepository{
		db:    db,
		redis: redisClient,
	}
}

func (r *bookingRepository) CreateBooking(booking *domain.Booking) error {
	return r.db.Create(booking).Error
}

func (r *bookingRepository) CreateBookingAddon(addon *domain.BookingAddon) error {
	return r.db.Create(addon).Error
}

func (r *bookingRepository) GetBookingByID(id string) (*domain.Booking, error) {
	var booking domain.Booking
	// Preload Addons untuk menarik data layanan tambahan sekaligus
	err := r.db.Preload("Addons").Where("id = ?", id).First(&booking).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, errors.New("booking not found")
		}
		return nil, err
	}
	return &booking, nil
}

func (r *bookingRepository) UpdateBooking(booking *domain.Booking) error {
	return r.db.Save(booking).Error
}

func (r *bookingRepository) GetRoomByID(id string) (*domain.Room, error) {
	var room domain.Room
	err := r.db.Where("id = ?", id).First(&room).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, errors.New("room not found")
		}
		return nil, err
	}
	return &room, nil
}

func (r *bookingRepository) GetAddonByID(id string) (*domain.Addon, error) {
	var addon domain.Addon
	err := r.db.Where("id = ?", id).First(&addon).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, errors.New("addon not found")
		}
		return nil, err
	}
	return &addon, nil
}

func (r *bookingRepository) GetGuestByID(id string) (*domain.Guest, error) {
	var guest domain.Guest
	err := r.db.Where("id = ?", id).First(&guest).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, errors.New("guest not found")
		}
		return nil, err
	}
	return &guest, nil
}

func (r *bookingRepository) UpdateBookingStatus(bookingID string, status string) error {
	return r.db.Model(&domain.Booking{}).Where("id = ?", bookingID).Update("status", status).Error
}

func (r *bookingRepository) UpdateRoomStatus(roomID string, status string) error {
	return r.db.Model(&domain.Room{}).Where("id = ?", roomID).Update("status", status).Error
}

// Redis operations
func (r *bookingRepository) HoldRoom(ctx context.Context, roomID string, guestID string, ttl time.Duration) error {
	key := "hold:room:" + roomID
	// SETNX (Set if Not eXists) memastikan hanya 1 orang yang berhasil
	ok, err := r.redis.SetNX(ctx, key, guestID, ttl).Result()
	if err != nil {
		return err
	}
	if !ok {
		return errors.New("kamar sedang ditahan oleh pengguna lain")
	}
	return nil
}

func (r *bookingRepository) ReleaseRoom(ctx context.Context, roomID string, guestID string) error {
	key := "hold:room:" + roomID
	
	// Get current hold
	currentGuest, err := r.redis.Get(ctx, key).Result()
	if err != nil {
		if errors.Is(err, redis.Nil) {
			return nil // Key tidak ada, sudah dirilis atau expire
		}
		return err
	}

	// Hanya boleh dirilis oleh guest yang memegang lock
	if currentGuest != guestID {
		return errors.New("anda tidak memiliki hak untuk membebaskan kamar ini")
	}

	return r.redis.Del(ctx, key).Err()
}

func (r *bookingRepository) GetRoomHold(ctx context.Context, roomID string) (string, error) {
	key := "hold:room:" + roomID
	val, err := r.redis.Get(ctx, key).Result()
	if err != nil {
		if errors.Is(err, redis.Nil) {
			return "", nil // Tidak sedang di-hold
		}
		return "", err
	}
	return val, nil
}
