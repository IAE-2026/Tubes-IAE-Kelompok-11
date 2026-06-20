package infrastructure

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"time"
)

// =============================================================================
// Inter-Service HTTP Client
// Digunakan oleh Reservasi Service untuk memanggil Catalog & Guest Service
// secara internal melalui Docker private network.
//
// Setiap request menyuntikkan header X-INTERNAL-KEY agar diterima oleh
// middleware internal.key di masing-masing Laravel service.
// =============================================================================

// ExternalRoomData merepresentasikan data kamar yang dikembalikan oleh Catalog Service.
// Field disesuaikan dengan response JSON dari GET /internal/rooms/{id} milik Izaz.
type ExternalRoomData struct {
	ID            string  `json:"id"`
	Name          string  `json:"name"`
	Location      string  `json:"location"`
	Description   string  `json:"description"`
	PricePerNight float64 `json:"price,string"`
	Status        string  `json:"status"`
}

// ExternalGuestData merepresentasikan data tamu yang dikembalikan oleh Guest Service.
// Field disesuaikan dengan response JSON dari GET /internal/{guestId} milik Calista.
type ExternalGuestData struct {
	ID          string `json:"id"`
	Name        string `json:"name"`
	Email       string `json:"email"`
	KtpNumber   string `json:"ktp_number"`
	PhoneNumber string `json:"phone_number"`
}

// internalHTTPClient adalah shared HTTP client dengan timeout yang wajar.
var internalHTTPClient = &http.Client{
	Timeout: 5 * time.Second,
}

// doInternalGET adalah helper internal untuk melakukan HTTP GET ke service lain
// dengan menyuntikkan header X-INTERNAL-KEY secara otomatis.
func doInternalGET(url string) ([]byte, int, error) {
	internalKey := os.Getenv("INTERNAL_SERVICE_KEY")
	if internalKey == "" {
		return nil, 0, fmt.Errorf("INTERNAL_SERVICE_KEY belum dikonfigurasi di environment")
	}

	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return nil, 0, fmt.Errorf("gagal membuat request ke %s: %w", url, err)
	}

	req.Header.Set("X-INTERNAL-KEY", internalKey)
	req.Header.Set("Accept", "application/json")

	resp, err := internalHTTPClient.Do(req)
	if err != nil {
		return nil, 0, fmt.Errorf("gagal menghubungi service di %s: %w", url, err)
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, resp.StatusCode, fmt.Errorf("gagal membaca response body dari %s: %w", url, err)
	}

	return body, resp.StatusCode, nil
}

// FetchRoomFromCatalog memanggil Catalog Service secara internal untuk
// memvalidasi dan mengambil detail kamar berdasarkan roomID.
//
// URL target: http://catalog-service:8000/internal/rooms/{roomID}
// Returns error jika kamar tidak ditemukan (404) atau service tidak dapat dijangkau.
func FetchRoomFromCatalog(roomID string) (*ExternalRoomData, error) {
	catalogURL := os.Getenv("CATALOG_SERVICE_URL")
	if catalogURL == "" {
		return nil, fmt.Errorf("CATALOG_SERVICE_URL belum dikonfigurasi di environment")
	}

	url := fmt.Sprintf("%s/api/internal/rooms/%s", catalogURL, roomID)
	body, statusCode, err := doInternalGET(url)
	if err != nil {
		return nil, fmt.Errorf("inter-service error ke Catalog: %w", err)
	}

	if statusCode == http.StatusNotFound {
		return nil, fmt.Errorf("kamar dengan ID %s tidak ditemukan di Catalog Service", roomID)
	}
	if statusCode != http.StatusOK {
		return nil, fmt.Errorf("Catalog Service merespon dengan status %d: %s", statusCode, string(body))
	}

	// Response Catalog Service mungkin dibungkus dalam envelope JSON standar Laravel.
	// Coba parse langsung sebagai ExternalRoomData, lalu coba parse sebagai envelope.
	var room ExternalRoomData
	if err := json.Unmarshal(body, &room); err == nil && room.ID != "" {
		return &room, nil
	}

	// Fallback: parse sebagai envelope { "data": {...} }
	var envelope struct {
		Data ExternalRoomData `json:"data"`
	}
	if err := json.Unmarshal(body, &envelope); err != nil {
		return nil, fmt.Errorf("gagal parse response Catalog Service: %w", err)
	}
	if envelope.Data.ID == "" {
		return nil, fmt.Errorf("response Catalog Service tidak mengandung data kamar yang valid")
	}

	return &envelope.Data, nil
}

// FetchGuestFromGuestService memanggil Guest Service secara internal untuk
// memvalidasi dan mengambil detail tamu berdasarkan guestID.
//
// URL target: http://guest-service:8000/api/internal/{guestID}
// Returns error jika tamu tidak ditemukan (404) atau service tidak dapat dijangkau.
func FetchGuestFromGuestService(guestID string) (*ExternalGuestData, error) {
	guestURL := os.Getenv("GUEST_SERVICE_URL")
	if guestURL == "" {
		return nil, fmt.Errorf("GUEST_SERVICE_URL belum dikonfigurasi di environment")
	}

	url := fmt.Sprintf("%s/api/internal/%s", guestURL, guestID)
	body, statusCode, err := doInternalGET(url)
	if err != nil {
		return nil, fmt.Errorf("inter-service error ke Guest Service: %w", err)
	}

	if statusCode == http.StatusNotFound {
		return nil, fmt.Errorf("tamu dengan ID %s tidak ditemukan di Guest Service", guestID)
	}
	if statusCode != http.StatusOK {
		return nil, fmt.Errorf("Guest Service merespon dengan status %d: %s", statusCode, string(body))
	}

	// Coba parse langsung sebagai ExternalGuestData
	var guest ExternalGuestData
	if err := json.Unmarshal(body, &guest); err == nil && guest.ID != "" {
		return &guest, nil
	}

	// Fallback: parse sebagai envelope { "data": {...} }
	var envelope struct {
		Data ExternalGuestData `json:"data"`
	}
	if err := json.Unmarshal(body, &envelope); err != nil {
		return nil, fmt.Errorf("gagal parse response Guest Service: %w", err)
	}
	if envelope.Data.ID == "" {
		return nil, fmt.Errorf("response Guest Service tidak mengandung data tamu yang valid")
	}

	return &envelope.Data, nil
}
