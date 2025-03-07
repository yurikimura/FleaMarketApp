# フリマアプリ

## 環境構築

### 初期構築
```
docker compose up -d --build
docker compose exec php composer install
docker compose exec php cp .env.example .env
docker compose exec php php artisan key:generate
docker compose exec php php artisan storage:link
docker compose exec php chmod -R 777 storage bootstrap/cache
```

### マイグレーション
```
docker compose exec php php artisan migrate:fresh --seed
```

### 停止
```
docker compose down --remove-orphans
```

### 起動
```
docker compose up -d
```

### キャッシュクリア
```
docker compose exec php php artisan cache:clear 
```

### 設定キャッシュ
```
docker compose exec php php artisan config:cache 
```

## 使用技術(実行環境)
- PHP 7.4.9
- Laravel 8.83.8
- MySQL 10.3.39

## ER 図

<img width="548" alt="Image" src="https://github.com/user-attachments/assets/92f542a5-44ff-4165-b6f7-472b1693a5fa" />

- ホーム画面 http://localhost/
- phpMyAdmin : http://localhost:8080/
