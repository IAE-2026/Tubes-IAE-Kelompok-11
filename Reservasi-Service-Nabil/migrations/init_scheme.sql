-- Extension untuk UUID
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Hapus tabel lama jika ada agar mendapat database yang benar-benar bersih (Fresh Install)
DROP TABLE IF EXISTS booking_addons CASCADE;
DROP TABLE IF EXISTS bookings CASCADE;
DROP TABLE IF EXISTS addons CASCADE;
DROP TABLE IF EXISTS rooms CASCADE;
DROP TABLE IF EXISTS guests CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- 1. Tabel Users (Authentication & Role Mapping)
-- Digunakan oleh middleware auth untuk mapping email JWT ke role lokal
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'guest', -- guest, admin
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Guest (Guest Service)
CREATE TABLE IF NOT EXISTS guests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    ktp_number VARCHAR(20) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabel Rooms (Catalog Service)
CREATE TABLE IF NOT EXISTS rooms (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT,
    facilities JSONB, -- Menyimpan daftar fasilitas dalam format JSON
    price_per_night DECIMAL(12, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'AVAILABLE', -- AVAILABLE, BOOKED, MAINTENANCE
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tabel Addons (Catalog Service)
CREATE TABLE IF NOT EXISTS addons (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    price DECIMAL(12, 2) NOT NULL,
    description TEXT
);

-- 5. Tabel Bookings (Booking Service)
-- Mengelola logika penguncian kamar (locking)
CREATE TABLE IF NOT EXISTS bookings (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    guest_id UUID REFERENCES guests(id),
    room_id UUID REFERENCES rooms(id),
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    total_room_price DECIMAL(12, 2) NOT NULL,
    total_addons_price DECIMAL(12, 2) DEFAULT 0,
    grand_total DECIMAL(12, 2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'LOCKED', -- LOCKED, PENDING_PAYMENT, CONFIRMED, CANCELLED
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP, -- Batas waktu pembayaran sebelum kunci dilepas
    receipt_number VARCHAR(100) -- Nomor resi dari audit SOAP
);

-- 6. Tabel Relasi Booking Addons (Booking Service)
CREATE TABLE IF NOT EXISTS booking_addons (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    booking_id UUID REFERENCES bookings(id) ON DELETE CASCADE,
    addon_id UUID REFERENCES addons(id),
    quantity INTEGER DEFAULT 1,
    price_at_booking DECIMAL(12, 2) NOT NULL -- Menyimpan harga saat booking agar tidak terpengaruh perubahan harga katalog
);