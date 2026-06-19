package infrastructure

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"time"
)

// BookingEvent merepresentasikan payload yang akan di-publish ke message broker.
// Field GuestName, GuestEmail, RoomName, RoomPrice, dan ReceiptNumber
// diperkaya dari hasil inter-service call ke Guest Service & Catalog Service.
type BookingEvent struct {
	BookingID     string    `json:"booking_id"`
	Status        string    `json:"status"`
	Timestamp     time.Time `json:"timestamp"`
	// Data tamu — diisi dari inter-service call ke Guest Service
	GuestName     string    `json:"guest_name,omitempty"`
	GuestEmail    string    `json:"guest_email,omitempty"`
	// Data kamar — diisi dari inter-service call ke Catalog Service
	RoomName      string    `json:"room_name,omitempty"`
	RoomPrice     float64   `json:"room_price_per_night,omitempty"`
	// Data audit — diisi setelah SOAP berhasil
	ReceiptNumber string    `json:"receipt_number,omitempty"`
}

// PublishBookingEvent mengirimkan event Booking ke RabbitMQ via HTTP Gateway Server Dosen
func PublishBookingEvent(ctx context.Context, event BookingEvent) error {
	ssoURL := os.Getenv("SSO_URL")
	if ssoURL == "" {
		return fmt.Errorf("SSO_URL belum diset di environment variables")
	}

	// 1. Ambil M2M Token untuk Authorization
	token, err := GetM2MToken(ctx)
	if err != nil {
		return fmt.Errorf("gagal mendapatkan M2M token: %w", err)
	}

	// 2. Serialisasi Event ke format JSON
	wrappedPayload := map[string]interface{}{
		"message": event,
	}
	payloadBytes, err := json.Marshal(wrappedPayload)
	if err != nil {
		return fmt.Errorf("gagal serialisasi event payload: %w", err)
	}

	// 3. Konfigurasi HTTP Request
	publishURL := fmt.Sprintf("%s/api/v1/messages/publish", ssoURL)
	req, err := http.NewRequestWithContext(ctx, "POST", publishURL, bytes.NewBuffer(payloadBytes))
	if err != nil {
		return fmt.Errorf("gagal membuat request publish event: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", fmt.Sprintf("Bearer %s", token))

	// 4. Eksekusi Request
	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("gagal hit api message broker: %w", err)
	}
	defer resp.Body.Close()

	// 5. Validasi Respons
	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated && resp.StatusCode != http.StatusAccepted {
		body, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("server message broker merespon dengan status %d: %s", resp.StatusCode, string(body))
	}

	return nil
}
