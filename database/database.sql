INSERT INTO users (name, email, password, created_at, updated_at)
VALUES (
    'Administrador',
    'admin@ejemplo.com',
    SHA2('admin123', 256),
    NOW(),
    NOW()
);

INSERT INTO productos (sku, nombre, descripcion, cantidad, precio, total, fecha_creacion, created_at, updated_at)
VALUES
(1001, 'Teclado mecánico', 'Teclado RGB con switches rojos', 5, 120.00, 600.00, NOW(), NOW(), NOW()),
(1002, 'Mouse gamer', 'Mouse con 8 botones programables', 10, 45.50, 455.00, NOW(), NOW(), NOW()),
(1003, 'Monitor 24"', 'Monitor Full HD IPS', 3, 180.99, 542.97, NOW(), NOW(), NOW()),
(1004, 'Silla ergonómica', 'Silla de oficina con soporte lumbar', 2, 220.00, 440.00, NOW(), NOW(), NOW()),
(1005, 'Audífonos Bluetooth', 'Con cancelación de ruido', 8, 99.90, 799.20, NOW(), NOW(), NOW()),
(1006, 'Laptop 15"', 'Intel i5, 8GB RAM', 4, 750.00, 3000.00, NOW(), NOW(), NOW()),
(1007, 'Webcam HD', 'Cámara 1080p para videollamadas', 7, 39.99, 279.93, NOW(), NOW(), NOW()),
(1008, 'Micrófono USB', 'Micrófono para streaming', 6, 79.00, 474.00, NOW(), NOW(), NOW()),
(1009, 'Disco SSD 1TB', 'Unidad NVMe de alta velocidad', 9, 115.00, 1035.00, NOW(), NOW(), NOW()),
(1010, 'Router WiFi 6', 'Router de alto rendimiento', 3, 129.99, 389.97, NOW(), NOW(), NOW());
