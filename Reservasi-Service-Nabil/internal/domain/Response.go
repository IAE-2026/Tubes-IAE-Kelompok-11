package domain

// SuccessResponse adalah standar wrapper untuk respon 2xx
type SuccessResponse struct {
	Status  string      `json:"status"`  // Biasanya diisi "success"
	Message string      `json:"message"` // Pesan sukses
	Data    interface{} `json:"data"`    // Objek atau Array data utama
	Meta    *Meta       `json:"meta,omitempty"` // Informasi tambahan (opsional)
}

// Meta berisi informasi layanan dan versi
type Meta struct {
	ServiceName string `json:"service_name"`
	ApiVersion  string `json:"api_version"`
}

// ErrorResponse adalah standar wrapper untuk respon 4xx/5xx
type ErrorResponse struct {
	Status  string      `json:"status"`  // Biasanya diisi "error" atau "fail"
	Message string      `json:"message"` // Detail pesan kesalahan
	Errors  interface{} `json:"errors,omitempty"` // Opsional: Detail error validasi
}