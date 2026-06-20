package infrastructure

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"
)

// SSOTokenResponse merepresentasikan JSON response dari SSO server.
// Membaca `token` atau `access_token` dari respons JSON.
type SSOTokenResponse struct {
	Token       string `json:"token"`
	AccessToken string `json:"access_token"`
}

// GetM2MToken mengambil token M2M dari Redis jika ada, atau mengambil yang baru dari SSO server
func GetM2MToken(ctx context.Context) (string, error) {
	cacheKey := "sso:m2m_token"

	// 1. Cek di Redis terlebih dahulu
	if RedisClient != nil {
		cachedToken, err := RedisClient.Get(ctx, cacheKey).Result()
		if err == nil && cachedToken != "" {
			return cachedToken, nil // Cache hit
		}
	}

	// 2. Cache miss, fetch dari SSO
	ssoURL := os.Getenv("SSO_URL")
	apiKey := os.Getenv("API_KEY")

	if ssoURL == "" || apiKey == "" {
		return "", errors.New("SSO_URL atau API_KEY belum diset di environment variables")
	}

	payload := fmt.Sprintf("api_key=%s&nim=102022430014", apiKey)

	req, err := http.NewRequestWithContext(ctx, "POST", fmt.Sprintf("%s/api/v1/auth/token", ssoURL), strings.NewReader(payload))
	if err != nil {
		return "", fmt.Errorf("gagal membuat request SSO: %v", err)
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("gagal memanggil server SSO: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		return "", fmt.Errorf("server SSO mengembalikan status %d: %s", resp.StatusCode, string(body))
	}

	var tokenResp SSOTokenResponse
	if err := json.NewDecoder(resp.Body).Decode(&tokenResp); err != nil {
		return "", fmt.Errorf("gagal decode response SSO: %v", err)
	}

	token := tokenResp.Token
	if token == "" {
		token = tokenResp.AccessToken
	}
	if token == "" {
		return "", errors.New("token tidak ditemukan dalam response SSO")
	}

	// 3. Ekstrak 'exp' dari JWT untuk menentukan TTL cache
	ttl := extractJWTTTL(token)
	if ttl <= 0 {
		// Fallback TTL jika parsing gagal atau token sudah expired (misal 1 jam)
		ttl = 1 * time.Hour
	}

	// 4. Simpan ke Redis (Kurangi sedikit TTL sebagai buffer, misalnya kurangi 1 menit agar tidak mepet)
	if ttl > 1*time.Minute {
		ttl -= 1 * time.Minute
	}
	if RedisClient != nil {
		if err := RedisClient.Set(ctx, cacheKey, token, ttl).Err(); err != nil {
			log.Printf("Warning: gagal menyimpan M2M token ke Redis: %v\n", err)
		}
	}

	return token, nil
}

// extractJWTTTL mem-parsing payload JWT untuk mendapatkan sisa waktu aktif token (TTL)
func extractJWTTTL(token string) time.Duration {
	parts := strings.Split(token, ".")
	if len(parts) != 3 {
		return 0
	}

	// Payload adalah bagian kedua dari JWT
	payloadPart := parts[1]

	// JWT menggunakan base64 raw url encoding
	payloadBytes, err := base64.RawURLEncoding.DecodeString(payloadPart)
	if err != nil {
		return 0
	}

	var claims struct {
		Exp int64 `json:"exp"`
	}
	if err := json.Unmarshal(payloadBytes, &claims); err != nil {
		return 0
	}

	if claims.Exp == 0 {
		return 0
	}

	// Hitung sisa waktu
	expirationTime := time.Unix(claims.Exp, 0)
	ttl := time.Until(expirationTime)

	return ttl
}
