package infrastructure

import (
	"context"
	"log"
	"os"

	"github.com/redis/go-redis/v9"
)

// RedisClient adalah instance global dari klien Redis
var RedisClient *redis.Client

// ConnectRedis menginisialisasi koneksi ke server Redis
func ConnectRedis() {
	addr := os.Getenv("REDIS_ADDR")
	if addr == "" {
		addr = "localhost:6379" // Default ke localhost jika REDIS_ADDR tidak diset
	}

	RedisClient = redis.NewClient(&redis.Options{
		Addr:     addr,
		Password: "", // Kosongkan jika Redis lokal tidak dipassword
		DB:       0,  // Gunakan default DB
	})

	// Menguji koneksi dengan Ping
	ctx := context.Background()
	if err := RedisClient.Ping(ctx).Err(); err != nil {
		log.Fatalf("Gagal terhubung ke Redis: %v", err)
	}

	log.Println("Koneksi ke Redis berhasil!")
}
