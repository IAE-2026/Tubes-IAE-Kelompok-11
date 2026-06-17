// @title Layanan Reservasi API
// @version 1.0
// @description API untuk layanan reservasi booking hotel (IAE Tubes)
// @host localhost:8080
// @BasePath /
package main

import (
	"bufio"
	"flag"
	"fmt"
	"log"
	"net/http"
	"os"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/joho/godotenv"
	"reservasi/internal/delivery/graphql"
	"reservasi/internal/delivery/rest"
	"reservasi/internal/domain"
	"reservasi/internal/infrastructure"
	"reservasi/internal/repository"
	"reservasi/internal/usecase"
	"reservasi/internal/worker"
	"reservasi/pkg/middleware"

	_ "reservasi/docs"

	"github.com/99designs/gqlgen/graphql/handler"
	"github.com/99designs/gqlgen/graphql/playground"

	swaggerFiles "github.com/swaggo/files"
	ginSwagger "github.com/swaggo/gin-swagger"
)

func main() {
	// ============================================================
	// FLAG: go run cmd/app/main.go --migrate --seed
	// ============================================================
	flagMigrate := flag.Bool("migrate", false, "Langsung jalankan migrasi tabel tanpa konfirmasi")
	flagSeed := flag.Bool("seed", false, "Langsung jalankan seed data tanpa konfirmasi")
	flag.Parse()

	// 1. Muat environment variable dari .env
	if err := godotenv.Load("configs/.env"); err != nil {
		log.Println("Warning: File configs/.env tidak ditemukan, menggunakan variabel environment sistem.")
	}

	// 2. Inisialisasi koneksi Database, Redis, dan Message Broker
	infrastructure.ConnectPostgres()
	infrastructure.ConnectRedis()

	// ============================================================
	// 3. Proses Migrasi & Seed (Interaktif atau via Flag)
	// ============================================================
	// Baca environment variables untuk Docker auto-migrate
	autoMigrate := os.Getenv("APP_AUTO_MIGRATE") == "true"
	autoSeed := os.Getenv("APP_AUTO_SEED") == "true"

	doMigrate := *flagMigrate || autoMigrate
	doSeed := *flagSeed || autoSeed

	// Jika tidak ada flag yang diberikan dan bukan auto, tanya admin secara interaktif
	if !doMigrate && !doSeed {
		reader := bufio.NewReader(os.Stdin)

		fmt.Print("\n[?] Apakah ingin menjalankan migrasi tabel ke database? (y/N): ")
		migrateInput, _ := reader.ReadString('\n')
		migrateInput = strings.TrimSpace(strings.ToLower(migrateInput))
		if migrateInput == "y" || migrateInput == "yes" {
			doMigrate = true
		}

		fmt.Print("[?] Apakah ingin memasukkan data dummy dari seed_data.sql? (y/N): ")
		seedInput, _ := reader.ReadString('\n')
		seedInput = strings.TrimSpace(strings.ToLower(seedInput))
		if seedInput == "y" || seedInput == "yes" {
			doSeed = true
		}
		fmt.Println()
	}

	// Eksekusi Migrasi
	if doMigrate {
		log.Println("Menjalankan migrasi tabel database...")
		sqlBytes, err := os.ReadFile("migrations/init_scheme.sql")
		if err != nil {
			log.Fatalf("Gagal membaca file init_scheme.sql: %v", err)
		}
		if err := infrastructure.DB.Exec(string(sqlBytes)).Error; err != nil {
			log.Printf("Warning saat migrasi (mungkin tabel sudah ada): %v", err)
		} else {
			log.Println("Migrasi tabel database berhasil!")
		}
	} else {
		log.Println("Migrasi tabel dilewati.")
	}

	// Eksekusi Seed
	if doSeed {
		log.Println("Memasukkan data dummy dari seed_data.sql...")
		sqlBytes, err := os.ReadFile("migrations/seed_data.sql")
		if err != nil {
			log.Fatalf("Gagal membaca file seed_data.sql: %v", err)
		}
		if err := infrastructure.DB.Exec(string(sqlBytes)).Error; err != nil {
			log.Printf("Warning saat seeding: %v", err)
		} else {
			log.Println("Seed data berhasil dimasukkan!")
		}
	} else {
		log.Println("Seed data dilewati.")
	}

	// 4. Inisialisasi Dependency Injection (Clean Architecture)
	bookingRepo := repository.NewBookingRepository(infrastructure.DB, infrastructure.RedisClient)
	bookingUsecase := usecase.NewBookingUsecase(bookingRepo)

	// 5b. Start background worker untuk Outbox Pattern (Retry Queue)
	worker.StartRetryWorker(bookingRepo)

	// 6. Inisialisasi Router Gin
	r := gin.Default()

	// 6. Swagger UI & GraphQL Playground (didaftarkan publik)
	r.GET("/swagger/*any", ginSwagger.WrapHandler(swaggerFiles.Handler))
	r.GET("/graphql", func(c *gin.Context) {
		h := playground.Handler("GraphQL", "/graphql/v1/summary")
		h.ServeHTTP(c.Writer, c.Request)
	})

	// Status endpoint publik
	r.GET("/status", func(c *gin.Context) {
		res := domain.SuccessResponse{
			Status:  "success",
			Message: "Layanan Reservasi API Online",
		}
		c.JSON(http.StatusOK, res)
	})

	// 7. Buat GraphQL server sekali untuk handler terproteksi
	graphqlServer := handler.NewDefaultServer(graphql.NewExecutableSchema(graphql.Config{
		Resolvers: &graphql.Resolver{
			BookingUsecase: bookingUsecase,
		},
	}))

	// 8. Daftarkan middleware autentikasi untuk route terproteksi
	protected := r.Group("/")
	protected.Use(middleware.AuthMiddleware())

	protected.POST("/graphql/v1/summary", func(c *gin.Context) {
		graphqlServer.ServeHTTP(c.Writer, c.Request)
	})

	// 9. Daftarkan Handler Booking Service (REST Handlers)
	rest.NewBookingHandler(protected, bookingUsecase)

	// 9. Jalankan Server
	port := os.Getenv("APP_PORT")
	if port == "" {
		port = "8080"
	}
	log.Printf("Server berjalan di port %s...", port)
	r.Run(":" + port)
}
