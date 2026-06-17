package domain

import (
	"time"

	"github.com/google/uuid"
)

type Guest struct {
	ID          uuid.UUID `gorm:"type:uuid;primaryKey;default:uuid_generate_v4()" json:"id"`
	Name        string    `gorm:"type:varchar(255);not null" json:"name"`
	Email       string    `gorm:"type:varchar(255);unique;not null" json:"email"`
	KtpNumber   string    `gorm:"type:varchar(20);unique;not null" json:"ktp_number"`
	PhoneNumber string    `gorm:"type:varchar(20)" json:"phone_number"`
	CreatedAt   time.Time `gorm:"autoCreateTime" json:"created_at"`
}

type Room struct {
	ID            uuid.UUID `gorm:"type:uuid;primaryKey;default:uuid_generate_v4()" json:"id"`
	Name          string    `gorm:"type:varchar(100);not null" json:"name"`
	Location      string    `gorm:"type:varchar(255);not null" json:"location"`
	Description   string    `gorm:"type:text" json:"description"`
	Facilities    string    `gorm:"type:jsonb" json:"facilities"`
	PricePerNight float64   `gorm:"type:decimal(12,2);not null" json:"price_per_night"`
	Status        string    `gorm:"type:varchar(20);default:'AVAILABLE'" json:"status"`
	CreatedAt     time.Time `gorm:"autoCreateTime" json:"created_at"`
}

type Addon struct {
	ID          uuid.UUID `gorm:"type:uuid;primaryKey;default:uuid_generate_v4()" json:"id"`
	Name        string    `gorm:"type:varchar(100);not null" json:"name"`
	Price       float64   `gorm:"type:decimal(12,2);not null" json:"price"`
	Description string    `gorm:"type:text" json:"description"`
}
