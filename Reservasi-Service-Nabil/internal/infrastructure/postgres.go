package infrastructure

import (
	"fmt"
	"log"
	"os"

	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

// DB adalah instance global dari koneksi database GORM
var DB *gorm.DB

// ConnectPostgres menginisialisasi koneksi ke PostgreSQL menggunakan GORM
func ConnectPostgres() {
	host := os.Getenv("DB_HOST")
	port := os.Getenv("DB_PORT")
	user := os.Getenv("DB_USER")
	password := os.Getenv("DB_PASS")
	dbname := os.Getenv("DB_NAME")

	// DSN (Data Source Name) untuk PostgreSQL
	dsn := fmt.Sprintf("host=%s user=%s password=%s dbname=%s port=%s sslmode=disable TimeZone=Asia/Jakarta",
		host, user, password, dbname, port)

	var err error
	DB, err = gorm.Open(postgres.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("Gagal terhubung ke database PostgreSQL: %v", err)
	}

	// Aktifkan extension untuk UUID jika belum ada
	_ = DB.Exec("CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\"")

	log.Println("Koneksi ke PostgreSQL berhasil menggunakan GORM!")
}
