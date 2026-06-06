# UAS Administrasi Server & Cloud Computing
## Zero-Touch CI/CD Deployment — GitHub Actions + Docker + AWS EC2

---

## Struktur Proyek

```
uas-project/
├── .github/
│   └── workflows/
│       └── ci-cd.yml          ← Pipeline CI/CD (Paths Filter + Anti-Conflict)
├── web-statis/
│   ├── Dockerfile             ← Nginx image
│   ├── nginx.conf             ← Custom Nginx config
│   └── index.html             ← Web CV/Portfolio
├── web-dinamis/
│   ├── Dockerfile             ← PHP 8.2 Apache image
│   ├── config.php             ← Koneksi PDO (singleton)
│   ├── index.php              ← CRUD Mahasiswa
│   └── sql/
│       └── init.sql           ← Schema + seeding data awal
├── docker-compose.yml         ← Orkestrasi 3 service
├── .env.example               ← Template environment variables
└── README.md
```

---

## Setup Awal

### 1. Clone Repository

```bash
git clone https://github.com/USERNAME/REPO_NAME.git
cd uas-project
```

### 2. Buat file `.env`

```bash
cp .env.example .env
# Edit .env dengan nilai yang sesuai
nano .env
```

### 3. Jalankan Lokal dengan Docker Compose

```bash
docker compose up -d --build
```

- Web Statis  → http://localhost:80
- Web Dinamis → http://localhost:3000 (login: admin / admin123)
- MariaDB     → localhost:3306

---

## Konfigurasi GitHub Secrets

Tambahkan secrets berikut di Settings > Secrets > Actions:

| Secret Name         | Nilai                                    |
|---------------------|------------------------------------------|
| `DOCKERHUB_USERNAME`| Username Docker Hub Anda                 |
| `DOCKERHUB_TOKEN`   | Access token Docker Hub (bukan password) |
| `EC2_HOST`          | IP publik EC2 (contoh: 54.xxx.xxx.xxx)   |
| `EC2_USER`          | User SSH EC2 (biasanya: `ubuntu`)        |
| `EC2_SSH_KEY`       | Isi private key `.pem` (seluruh teks)    |

---

## Setup AWS EC2 (Ubuntu 22.04)

### Install Docker & Docker Compose

```bash
# Update sistem
sudo apt-get update && sudo apt-get upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker

# Verifikasi
docker --version
docker compose version
```

### Buka Port di Security Group

| Port | Protocol | Source    | Keterangan       |
|------|----------|-----------|------------------|
| 22   | TCP      | Your IP   | SSH              |
| 80   | TCP      | 0.0.0.0/0 | Web Statis       |
| 3000 | TCP      | 0.0.0.0/0 | Web Dinamis PHP  |

---

## Cara Kerja CI/CD Pipeline

```
Push ke main branch
        │
        ▼
[Job: changes] — dorny/paths-filter
        │
   ┌────┴────┐
   │         │
   ▼         ▼
[build_web_statis]  [build_web_dinamis]
Docker Build & Push  Docker Build & Push
ke Docker Hub        ke Docker Hub
   │         │
   └────┬────┘
        ▼
  [Job: deploy]
  SSH ke EC2
  ├─ git pull
  ├─ docker pull (image baru)
  ├─ docker rm -f (anti-conflict)
  ├─ docker compose down
  ├─ docker compose up -d
  └─ health check
```

### Keunggulan Paths Filter

- Push ke `web-statis/` → **hanya** build image web-statis
- Push ke `web-dinamis/` → **hanya** build image web-dinamis
- Push ke `docker-compose.yml` → trigger kedua build sekaligus

---

## Fitur Aplikasi PHP (web-dinamis)

- **Login/Logout** dengan session PHP
- **Dashboard** dengan statistik (total mahasiswa, rata-rata IPK, dll.)
- **CRUD Mahasiswa**: Create, Read, Update, Delete
- **Search** berdasarkan NIM, nama, atau jurusan
- **Pagination** (8 data per halaman)
- **Validasi** input server-side
- **Color-coded IPK** (hijau ≥3.5 / kuning ≥2.75 / merah <2.75)
- **Koneksi PDO** dengan prepared statements (anti SQL injection)

---

## Default Credentials

| Akun     | Username | Password |
|----------|----------|----------|
| Admin    | admin    | admin123 |
