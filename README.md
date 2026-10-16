# Callback Midtrans tidak masuk: contoh Laravel

[![tests](https://github.com/nextmend/callback-midtrans-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/nextmend/callback-midtrans-laravel/actions/workflows/tests.yml)

Contoh yang bisa dijalankan untuk artikel [Callback Midtrans tidak masuk: penyebab dan perbaikannya](https://nextmend.dev/artikel/callback-midtrans-tidak-masuk/?utm_source=github&utm_medium=readme&utm_campaign=artikel-callback).

Pembayaran sudah `settlement` di dashboard Midtrans, tetapi order di aplikasi tetap `pending`. Repo ini mereproduksi salah satu penyebabnya di Laravel: route notifikasi ada di `routes/web.php`, jadi kena pengecekan CSRF dan Midtrans menerima `419`. Laravel tidak mencatat error ini di log, dan test bawaan tetap hijau karena pengecekan CSRF dimatikan saat test.

## Isi

| File | Isi |
| --- | --- |
| `routes/web.php` | Endpoint `POST /midtrans/notification` |
| `app/Http/Controllers/MidtransNotificationController.php` | Verifikasi `signature_key`, lalu update order |
| `app/Models/Order.php` | `applyMidtransStatus()`: idempoten, notifikasi dobel atau terlambat tidak mengubah apa pun |
| `bootstrap/app.php` | Perbaikannya: route notifikasi dikecualikan dari CSRF |
| `routes/console.php` | `midtrans:reconcile`: rekonsiliasi lewat Get Status API, terjadwal tiap 5 menit |
| `tests/Feature/` | Test notifikasi dengan CSRF aktif seperti di production, dan test rekonsiliasi |
| `contoh/notifikasi-settlement.json` | Notifikasi contoh, ditandatangani dengan Server Key placeholder |

## Menjalankan

Butuh PHP 8.4+ dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Di terminal lain, kirim notifikasi seperti yang dilakukan Midtrans:

```bash
curl -i -X POST http://127.0.0.1:8000/midtrans/notification \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d @contoh/notifikasi-settlement.json
```

Test: `php artisan test`

## Langkah demi langkah

Setiap tahap punya tag. Jalankan `git checkout <tag>`, lalu `php artisan test` dan curl di atas.

| Tag | Isi | `php artisan test` | curl |
| --- | --- | --- | --- |
| `1-bug` | Route di `web.php`, test biasa | hijau | `419` |
| `2-test-merah` | Test mengaktifkan lagi CSRF | merah, `419` | `419` |
| `3-perbaikan` | Route dikecualikan dari CSRF | hijau | `200` |
| `main` | Ditambah rekonsiliasi Get Status API | hijau | `200` |

Ulangi `php artisan migrate:fresh --seed` sebelum curl kedua; order contoh sudah `paid` setelah curl pertama yang berhasil.

## Catatan

- `MIDTRANS_SERVER_KEY` di `.env.example` hanya placeholder. Untuk mencoba dengan sandbox Midtrans, isi `.env` dengan Server Key sandbox Anda dan jangan commit file itu.
- Ini contoh untuk belajar, bukan paket siap pakai. Sesuaikan status order dan efek sampingnya (email, stok) dengan aplikasi Anda.

Lisensi MIT. Dibuat oleh [Nextmend](https://nextmend.dev/?utm_source=github&utm_medium=readme&utm_campaign=artikel-callback).
