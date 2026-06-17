package middleware

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"

	"github.com/MicahParks/keyfunc/v2"
	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
	"github.com/google/uuid"
)

// UserContext menyimpan data user lokal untuk context
type UserContext struct {
	ID    uuid.UUID `gorm:"type:uuid"`
	Email string    `gorm:"type:varchar(100)"`
	Role  string    `gorm:"type:varchar(50)"`
}

// AuthMiddleware memvalidasi header X-IAE-KEY dan JWT Bearer
func AuthMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		// 1. Validasi header X-IAE-KEY
		key := c.GetHeader("X-IAE-KEY")
		expectedKey := os.Getenv("IAE_KEY")
		if expectedKey == "" {
			expectedKey = "102022430014" // Nilai wajib fallback
		}

		if key != expectedKey {
			abortWithError(c, http.StatusUnauthorized, "Unauthorized: Invalid or missing X-IAE-KEY header")
			return
		}

		// 2. Mengambil JWT dari header Authorization: Bearer <token>
		authHeader := c.GetHeader("Authorization")
		if authHeader == "" || !strings.HasPrefix(authHeader, "Bearer ") {
			abortWithError(c, http.StatusUnauthorized, "Unauthorized: Missing or invalid Authorization Bearer token")
			return
		}

		tokenStr := strings.TrimPrefix(authHeader, "Bearer ")

		// 3. Ambil JWKS dari cache/sso
		jwksBytes, err := getJWKS(c.Request.Context())
		if err != nil {
			log.Printf("Gagal mendapatkan JWKS: %v", err)
			abortWithError(c, http.StatusInternalServerError, "Internal Server Error: Failed to fetch JWKS")
			return
		}

		// Parsing JWKS dengan keyfunc
		jwks, err := keyfunc.NewJSON(jwksBytes)
		if err != nil {
			log.Printf("Gagal parsing JWKS: %v", err)
			abortWithError(c, http.StatusInternalServerError, "Internal Server Error: Invalid JWKS payload")
			return
		}

		// 4. Verifikasi JWT
		token, err := jwt.Parse(tokenStr, jwks.Keyfunc)
		if err != nil || !token.Valid {
			abortWithError(c, http.StatusUnauthorized, "Unauthorized: Invalid token signature")
			return
		}

		// 5. Ekstrak Email dari klaim JWT
		claims, ok := token.Claims.(jwt.MapClaims)
		if !ok {
			abortWithError(c, http.StatusUnauthorized, "Unauthorized: Invalid token claims")
			return
		}

		// Cek email di root claims
		emailRaw, ok := claims["email"]
		if !ok || emailRaw == nil {
			// Jika tidak ada, cek di dalam object "profile" (format SSO Cloud Dosen)
			if profileRaw, ok := claims["profile"]; ok && profileRaw != nil {
				if profileMap, ok := profileRaw.(map[string]interface{}); ok {
					emailRaw = profileMap["email"]
				}
			}
		}

		if emailRaw == nil {
			abortWithError(c, http.StatusUnauthorized, "Unauthorized: Token missing email claim")
			return
		}
		
		email, ok := emailRaw.(string)
		if !ok || email == "" {
			abortWithError(c, http.StatusUnauthorized, "Unauthorized: Email claim is not a string or empty")
			return
		}

		// 6. Validasi role lokal menggunakan database
		var user UserContext
		err = infrastructure.DB.Table("users").Select("id, email, role").Where("email = ?", email).First(&user).Error
		if err != nil {
			log.Printf("User dengan email %s tidak ditemukan di db lokal: %v", email, err)
			abortWithError(c, http.StatusForbidden, "Forbidden: User not found or not mapped locally")
			return
		}

		// 7. Inject identitas ke Gin Context
		c.Set("user", user)
		c.Set("userEmail", user.Email)
		c.Set("userRole", user.Role)
		c.Set("userID", user.ID.String())

		// Jika valid, teruskan ke handler berikutnya
		c.Next()
	}
}

// abortWithError membungkus logic pengembalian error
func abortWithError(c *gin.Context, statusCode int, message string) {
	response := domain.ErrorResponse{
		Status:  "error",
		Message: message,
	}
	c.JSON(statusCode, response)
	c.Abort()
}

// getJWKS mengambil JWKS dari Redis (jika ada) atau fetch dari Cloud SSO
func getJWKS(ctx context.Context) (json.RawMessage, error) {
	cacheKey := "sso:jwks"

	// 1. Cek di Redis
	if infrastructure.RedisClient != nil {
		cachedJWKS, err := infrastructure.RedisClient.Get(ctx, cacheKey).Result()
		if err == nil && cachedJWKS != "" {
			return json.RawMessage(cachedJWKS), nil
		}
	}

	// 2. Fetch dari Cloud SSO
	ssoURL := os.Getenv("SSO_URL")
	if ssoURL == "" {
		return nil, fmt.Errorf("SSO_URL belum diset di environment variables")
	}

	jwksURL := fmt.Sprintf("%s/api/v1/auth/jwks", ssoURL)
	req, err := http.NewRequestWithContext(ctx, "GET", jwksURL, nil)
	if err != nil {
		return nil, fmt.Errorf("gagal membuat HTTP request JWKS: %w", err)
	}

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("gagal hit api JWKS: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("server SSO merespon dengan HTTP status %d", resp.StatusCode)
	}

	jwksBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("gagal membaca payload JSON JWKS: %w", err)
	}

	// 3. Cache di Redis selama 24 Jam
	if infrastructure.RedisClient != nil {
		if err := infrastructure.RedisClient.Set(ctx, cacheKey, string(jwksBytes), 24*time.Hour).Err(); err != nil {
			log.Printf("Warning: gagal menyimpan JWKS ke Redis: %v", err)
		}
	}

	return jwksBytes, nil
}
