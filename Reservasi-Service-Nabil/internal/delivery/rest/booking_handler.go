package rest

import (
	"net/http"
	"reservasi/internal/domain"

	"github.com/gin-gonic/gin"
)

type BookingHandler struct {
	bookingUsecase domain.BookingUsecase
}

// NewBookingHandler mendaftarkan endpoint untuk Booking Service
func NewBookingHandler(r gin.IRouter, us domain.BookingUsecase) {
	handler := &BookingHandler{
		bookingUsecase: us,
	}

	// Semua route terkait booking dan room
	// Asumsinya middleware autentikasi diterapkan pada group yang memanggil fungsi ini.

	// Room Hold / Lock routes
	r.POST("/rooms/:id/hold", handler.HoldRoom)
	r.DELETE("/rooms/:id/hold", handler.ReleaseRoom)

	r.POST("/bookings", handler.CreateBooking)
	r.POST("/bookings/:id/addons", handler.AddAddon)
	r.GET("/bookings/:id/summary", handler.GetSummary)
}

// CreateBooking godoc
// @Summary Membuat pesanan awal (mengunci kamar)
// @Description Endpoint untuk membuat reservasi awal dengan status LOCKED. Terintegrasi dengan SOAP Audit dan Message Broker.
// @Tags Bookings
// @Accept json
// @Produce json
// @Param X-IAE-KEY header string true "API Key"
// @Param Authorization header string true "Bearer JWT Token dari SSO"
// @Param Idempotency-Key header string false "Kunci unik untuk mencegah duplikasi request"
// @Param request body domain.CreateBookingRequest true "Payload Reservasi"
// @Success 201 {object} domain.SuccessResponse{data=domain.Booking} "Created"
// @Failure 400 {object} domain.ErrorResponse "Bad Request"
// @Failure 401 {object} domain.ErrorResponse "Unauthorized"
// @Router /bookings [post]
func (h *BookingHandler) CreateBooking(c *gin.Context) {
	var req domain.CreateBookingRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: "Payload tidak valid: " + err.Error(),
		})
		return
	}

	// Ambil Idempotency-Key dari header untuk mencegah duplikasi request
	req.IdempotencyKey = c.GetHeader("Idempotency-Key")

	booking, err := h.bookingUsecase.CreateBooking(&req)
	if err != nil {
		// Menggunakan StatusInternalServerError atau BadRequest tergantung err
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: err.Error(),
		})
		return
	}

	c.JSON(http.StatusCreated, domain.SuccessResponse{
		Status:  "success",
		Message: "Pesanan awal berhasil dibuat (Kamar dikunci)",
		Data:    booking,
	})
}

// AddAddon godoc
// @Summary Menambahkan layanan tambahan ke pesanan
// @Description Endpoint untuk menambahkan addon (sarapan, asuransi, dll) ke dalam booking yang sudah ada
// @Tags Bookings
// @Accept json
// @Produce json
// @Param X-IAE-KEY header string true "API Key"
// @Param Authorization header string true "Bearer JWT Token dari SSO"
// @Param id path string true "Booking ID (UUID)"
// @Param request body domain.CreateBookingAddonRequest true "Payload Addon"
// @Success 200 {object} domain.SuccessResponse{data=domain.BookingAddon} "Success"
// @Failure 400 {object} domain.ErrorResponse "Bad Request"
// @Failure 401 {object} domain.ErrorResponse "Unauthorized"
// @Router /bookings/{id}/addons [post]
func (h *BookingHandler) AddAddon(c *gin.Context) {
	bookingID := c.Param("id")

	var req domain.CreateBookingAddonRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: "Payload tidak valid: " + err.Error(),
		})
		return
	}

	addon, err := h.bookingUsecase.AddAddon(bookingID, &req)
	if err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: err.Error(),
		})
		return
	}

	c.JSON(http.StatusOK, domain.SuccessResponse{
		Status:  "success",
		Message: "Layanan tambahan berhasil dimasukkan ke tagihan",
		Data:    addon,
	})
}

// GetSummary godoc
// @Summary Menampilkan nota total (Summary)
// @Description Mendapatkan ringkasan rincian biaya kamar dan layanan tambahan
// @Tags Bookings
// @Produce json
// @Param X-IAE-KEY header string true "API Key"
// @Param Authorization header string true "Bearer JWT Token dari SSO"
// @Param id path string true "Booking ID (UUID)"
// @Success 200 {object} domain.SuccessResponse{data=domain.BookingSummary} "Success"
// @Failure 404 {object} domain.ErrorResponse "Not Found"
// @Failure 401 {object} domain.ErrorResponse "Unauthorized"
// @Router /bookings/{id}/summary [get]
func (h *BookingHandler) GetSummary(c *gin.Context) {
	bookingID := c.Param("id")

	summary, err := h.bookingUsecase.GetSummary(bookingID)
	if err != nil {
		c.JSON(http.StatusNotFound, domain.ErrorResponse{
			Status:  "error",
			Message: err.Error(),
		})
		return
	}

	c.JSON(http.StatusOK, domain.SuccessResponse{
		Status:  "success",
		Message: "Nota total berhasil diambil",
		Data:    summary,
	})
}

// HoldRoom godoc
// @Summary [Tambahan] Menahan sementara kamar (Fase 1 Booking)
// @Description Endpoint tambahan untuk menahan kamar menggunakan Redis selama 10 menit agar tidak diambil orang lain saat mengisi form.
// @Tags Rooms (Tambahan)
// @Accept json
// @Produce json
// @Param X-IAE-KEY header string true "API Key"
// @Param Authorization header string true "Bearer JWT Token dari SSO"
// @Param id path string true "Room ID (UUID)"
// @Param request body domain.HoldRoomRequest true "Payload Hold"
// @Success 200 {object} domain.SuccessResponse "Success"
// @Failure 400 {object} domain.ErrorResponse "Bad Request"
// @Failure 401 {object} domain.ErrorResponse "Unauthorized"
// @Router /rooms/{id}/hold [post]
func (h *BookingHandler) HoldRoom(c *gin.Context) {
	roomID := c.Param("id")

	var req domain.HoldRoomRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: "Payload tidak valid: " + err.Error(),
		})
		return
	}

	err := h.bookingUsecase.HoldRoom(roomID, &req)
	if err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: err.Error(),
		})
		return
	}

	c.JSON(http.StatusOK, domain.SuccessResponse{
		Status:  "success",
		Message: "Kamar berhasil ditahan sementara selama 10 menit. Silakan selesaikan form pemesanan.",
	})
}

// ReleaseRoom godoc
// @Summary [Tambahan] Melepaskan tahanan kamar (Batal Booking)
// @Description Endpoint tambahan untuk membebaskan kamar jika pengguna membatalkan isi form sebelum 10 menit.
// @Tags Rooms (Tambahan)
// @Accept json
// @Produce json
// @Param X-IAE-KEY header string true "API Key"
// @Param Authorization header string true "Bearer JWT Token dari SSO"
// @Param id path string true "Room ID (UUID)"
// @Param request body domain.HoldRoomRequest true "Payload Hold (membutuhkan guest_id)"
// @Success 200 {object} domain.SuccessResponse "Success"
// @Failure 400 {object} domain.ErrorResponse "Bad Request"
// @Failure 401 {object} domain.ErrorResponse "Unauthorized"
// @Router /rooms/{id}/hold [delete]
func (h *BookingHandler) ReleaseRoom(c *gin.Context) {
	roomID := c.Param("id")

	var req domain.HoldRoomRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: "Payload tidak valid: " + err.Error(),
		})
		return
	}

	err := h.bookingUsecase.ReleaseRoom(roomID, req.GuestID)
	if err != nil {
		c.JSON(http.StatusBadRequest, domain.ErrorResponse{
			Status:  "error",
			Message: err.Error(),
		})
		return
	}

	c.JSON(http.StatusOK, domain.SuccessResponse{
		Status:  "success",
		Message: "Tahanan kamar berhasil dilepas. Kamar tersedia kembali.",
	})
}
