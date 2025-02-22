# 初期化
```
docker compose up -d --build
docker compose exec php composer install
docker compose exec php cp .env.example .env
docker compose exec php php artisan key:generate
docker compose exec php php artisan storage:link
docker compose exec php chmod -R 777 storage bootstrap/cache
```

# マイグレーション
```
docker compose exec php php artisan migrate:fresh --seed
```

# 停止
```
docker compose down --remove-orphans
```

# 起動
```
docker compose up -d
```

# キャッシュクリア
```
docker compose exec php php artisan cache:clear 
```

# 設定キャッシュ
```
docker compose exec php php artisan config:cache 
```