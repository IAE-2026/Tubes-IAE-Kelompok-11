-- ============================================
-- SEED DATA: Data Dummy untuk Pengujian API
-- Jalankan SETELAH server menyala & migrasi selesai
-- ============================================

-- 1. Data Users (Authentication & Role Mapping)
-- Mapping email JWT ke role lokal untuk otorisasi
INSERT INTO users (id, email, role) VALUES
('d1b2c3d4-e5f6-7890-abcd-111111111111', 'nabil@example.com', 'admin'),
('d1b2c3d4-e5f6-7890-abcd-222222222222', 'fauzan@example.com', 'guest'),
('d1b2c3d4-e5f6-7890-abcd-333333333333', 'siti@example.com', 'guest'),
('d1b2c3d4-e5f6-7890-abcd-444444444444', 'warga06@ktp.iae.id', 'guest')
ON CONFLICT (id) DO NOTHING;

-- 2. Data Tamu (Guests)
INSERT INTO guests (id, name, email, ktp_number, phone_number) VALUES
('a1b2c3d4-e5f6-7890-abcd-111111111111', 'Nabil Fikry Khaidar', 'nabil@example.com', '3578012345670001', '081234567890'),
('a1b2c3d4-e5f6-7890-abcd-222222222222', 'Ahmad Fauzan', 'fauzan@example.com', '3578012345670002', '081234567891'),
('a1b2c3d4-e5f6-7890-abcd-333333333333', 'Siti Nurhaliza', 'siti@example.com', '3578012345670003', '081234567892'),
('a1b2c3d4-e5f6-7890-abcd-444444444444', 'Fitri Handayani', 'warga06@ktp.iae.id', '2026000006', '081234567896')
ON CONFLICT (id) DO NOTHING;

-- 3. Data Kamar (Rooms)
INSERT INTO rooms (id, name, location, description, facilities, price_per_night, status) VALUES
('b1b2c3d4-e5f6-7890-abcd-111111111111', 'Deluxe Room 101', 'Lantai 1, Sayap Timur', 'Kamar deluxe dengan pemandangan taman tropis', '["AC", "TV 43 inch", "WiFi", "Minibar", "Bathtub"]', 750000.00, 'AVAILABLE'),
('b1b2c3d4-e5f6-7890-abcd-222222222222', 'Superior Room 201', 'Lantai 2, Sayap Barat', 'Kamar superior dengan balkon pribadi', '["AC", "TV 32 inch", "WiFi", "Shower"]', 500000.00, 'AVAILABLE'),
('b1b2c3d4-e5f6-7890-abcd-333333333333', 'Suite Room 301', 'Lantai 3, Sayap Utara', 'Suite mewah dengan ruang tamu terpisah', '["AC", "TV 55 inch", "WiFi", "Minibar", "Jacuzzi", "Living Room"]', 1500000.00, 'AVAILABLE')
ON CONFLICT (id) DO NOTHING;

-- 4. Data Layanan Tambahan (Addons)
INSERT INTO addons (id, name, price, description) VALUES
('c1b2c3d4-e5f6-7890-abcd-111111111111', 'Sarapan Pagi', 150000.00, 'Buffet sarapan pagi all-you-can-eat (06:00 - 10:00)'),
('c1b2c3d4-e5f6-7890-abcd-222222222222', 'Asuransi Perjalanan', 75000.00, 'Perlindungan asuransi selama masa menginap'),
('c1b2c3d4-e5f6-7890-abcd-333333333333', 'Extra Bed', 200000.00, 'Kasur tambahan untuk 1 orang dewasa'),
('c1b2c3d4-e5f6-7890-abcd-444444444444', 'Airport Shuttle', 350000.00, 'Layanan antar-jemput dari/ke bandara'),
('c1b2c3d4-e5f6-7890-abcd-555555555555', 'Spa & Massage', 250000.00, 'Paket spa dan pijat relaksasi 60 menit')
ON CONFLICT (id) DO NOTHING;

-- Verifikasi data yang berhasil dimasukkan
SELECT 'Users: ' || COUNT(*) FROM users;
SELECT 'Guests: ' || COUNT(*) FROM guests;
SELECT 'Rooms: ' || COUNT(*) FROM rooms;
SELECT 'Addons: ' || COUNT(*) FROM addons;
