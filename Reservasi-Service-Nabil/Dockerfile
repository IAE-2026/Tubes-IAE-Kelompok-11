# Stage 1: Build the application
FROM golang:1.26.3-alpine AS builder

# Set working directory
WORKDIR /app

# Install dependensi OS dasar jika diperlukan
RUN apk add --no-cache git

# Copy file go.mod dan go.sum
COPY go.mod go.sum ./

# Download dependensi
RUN go mod download

# Copy seluruh source code
COPY . .

# Build aplikasi Golang
# CGO_ENABLED=0 agar menghasilkan binary static murni (kompatibel dengan alpine yang sangat minimalis)
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o booking-server cmd/app/main.go

# Stage 2: Run the application (Menggunakan image kosong yang super ringan)
FROM alpine:latest  

# Instal timezone data untuk akurasi waktu
RUN apk --no-cache add ca-certificates tzdata

WORKDIR /app

# Copy binary dari stage builder
COPY --from=builder /app/booking-server .

# Copy konfigurasi/script pendukung (opsional jika dibutuhkan oleh binary)
# Dalam kasus kita, main.go membaca file SQL di /migrations, jadi kita harus copy juga
COPY --from=builder /app/migrations ./migrations
COPY --from=builder /app/configs ./configs

# Export port
EXPOSE 7070

# Command untuk menjalankan aplikasi
CMD ["./booking-server"]