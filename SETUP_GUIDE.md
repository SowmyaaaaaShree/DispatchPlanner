# Dispatch Planner - Setup Guide

## Quick Start (Local Development)

### Prerequisites
- PHP 8.2+ with PDO SQLite extension
- Composer

### Setup Steps

1. **Navigate to project directory:**
   ```powershell
   cd d:\xampp\htdocs\DispatchPlanner
   ```

2. **Install dependencies:**
   ```powershell
   composer install
   ```

3. **Set up environment:**
   ```powershell
   copy .env.example .env
   ```
   The `.env` file is already configured for SQLite local development.

4. **Generate application key:**
   ```powershell
   php artisan key:generate
   ```

5. **Run database migrations:**
   ```powershell
   php artisan migrate:fresh
   ```

6. **Start the development server:**
   ```powershell
   php artisan serve --host=localhost --port=8000 --tries=1 --no-reload
   ```

   The application is now available at: **http://localhost:8000**

7. **Create LocalStack buckets with Terraform:**
   ```powershell
   cd terraform
   terraform init
   terraform apply -auto-approve
   ```

   The S3 buckets `dispatch-input` and `dispatch-output` are created for CSV ingestion and output.

## API Endpoints

### Health Check
```
GET http://localhost:8000/api/healthz
```
Response:
```json
{"status":"ok"}
```

### Create Dispatch Batch
```
POST http://localhost:8000/api/dispatch-batches
Content-Type: application/json

{
  "orders": [
    {
      "order_id": "123",
      "placed_at": "2023-05-01T10:00:00Z",
      "destination_address": "Mumbai, India",
      "destination_country": "IN",
      "total_value": 100.0,
      "total_value_currency": "INR",
      "weight_grams": 500,
      "payment_mode": "prepaid"
    }
  ]
}
```

### Get Dispatch Plan
```
GET http://localhost:8000/api/dispatch-batches/{batch_id}
```

### Recompute Dispatch Plan
```
POST http://localhost:8000/api/dispatch-batches/{batch_id}/recompute
```

## Testing

Run tests with:
```powershell
php artisan test
```

## Setting Up with Docker and S3 (Optional)

If you want to use Docker with LocalStack for S3 features:

### 1. Install Docker Desktop
- Download from: https://www.docker.com/products/docker-desktop
- Follow the installation wizard
- Enable WSL 2 if prompted
- Restart your computer

### 2. Switch to MySQL (Optional)

Edit `.env`:
```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=dispatch_planner
DB_USERNAME=root
DB_PASSWORD=password
```

### 3. Run Docker Compose
```powershell
docker compose up --build
```

Services started:
- **Laravel App**: http://localhost:8000
- **MySQL**: localhost:3306
- **LocalStack** (S3): http://localhost:4566

### 4. Set up S3 Buckets with Terraform
```powershell
cd terraform
terraform init
terraform apply
```

This creates:
- `dispatch-input` bucket for CSV uploads
- `dispatch-output` bucket for CSV output

### 5. Configure S3 in .env

Add these settings:
```
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_DEFAULT_REGION=us-east-1
AWS_ENDPOINT=http://localhost:4566
AWS_BUCKET=dispatch-output
```

## CSV Upload

### Using Local Storage
Place CSV files in: `storage/app/input/`

### Using S3 with LocalStack
Upload to `dispatch-input` bucket, then run:
```powershell
php artisan dispatch:process-daily
```

## Database Schema

### dispatch_batches
- `id`: Primary key
- `status`: pending, processing, processed, failed
- `processed_at`: Timestamp when batch was processed
- `created_at`, `updated_at`

### orders
- `id`: Primary key
- `dispatch_batch_id`: Foreign key to dispatch_batches
- `order_id`: External order identifier
- `placed_at`: When order was placed
- `destination_address`: Free-text address
- `destination_country`: IN or DE
- `total_value`: Order value
- `total_value_currency`: ISO 4217 code
- `weight_grams`: Package weight
- `payment_mode`: prepaid or cod
- `geocoded_city`: City from geocoding
- `geocoded_lat`, `geocoded_lon`: Coordinates
- `planned_dispatch_date`: Calculated dispatch date
- `is_deferred`: Weather-blocked or holiday-blocked
- `deferred_reason`: Reason for deferral
- `invoiced_value_local`: COD amount in local currency
- `invoiced_value_currency`: Local currency code
- `status`: pending, processed, failed

### dispatch_runs
- `id`: Primary key
- `dispatch_batch_id`: Foreign key
- `city`: Destination city
- `country`: Destination country
- `dispatch_date`: Date orders will be dispatched
- `total_invoiced_value_local`: Sum of COD amounts
- `total_invoiced_value_currency`: Local currency
- `weather_summary`: JSON with weather data

### order_dispatch_run (Pivot)
- Links orders to dispatch runs

## External API Integration

The application integrates with these free APIs:

1. **Nominatim** (https://nominatim.openstreetmap.org/)
   - Geocodes addresses to city + coordinates
   - No authentication required

2. **Nager.Date** (https://date.nager.at/)
   - Checks for public holidays by country
   - No authentication required

3. **Open-Meteo** (https://open-meteo.com/)
   - Fetches weather forecasts
   - Blocks dispatch if: precipitation > 20mm, temp < -10°C or > 45°C
   - No authentication required

4. **Frankfurter** (https://frankfurter.dev/)
   - Provides ECB currency exchange rates
   - For COD orders: converts to local currency (INR for India, EUR for Germany)
   - No authentication required

## Troubleshooting

### Port Already in Use
If port 8000 is in use, try:
```powershell
php artisan serve --host=localhost --port=8001 --tries=1 --no-reload
```

### Database Not Found
Ensure SQLite database file exists:
```powershell
php artisan migrate:fresh
```

### S3 Connection Error
In local development, the app automatically falls back to local storage if S3 is unavailable.

For Docker setup, ensure LocalStack is running:
```powershell
docker compose logs localstack
```

### External API Failures
If external APIs fail:
- Geocoding failure: Order marked as failed
- Holiday/weather failure: Defaults assume no holiday/bad weather
- Currency conversion failure: COD amount not set (prepaid not affected)

## Project Structure

```
app/
├── Http/
│   └── Controllers/
│       ├── DispatchBatchController.php
│       └── HealthController.php
├── Models/
│   ├── DispatchBatch.php
│   ├── Order.php
│   └── DispatchRun.php
├── Services/
│   ├── DispatchPlannerService.php
│   ├── GeocodingService.php
│   ├── HolidayService.php
│   ├── WeatherService.php
│   ├── CurrencyService.php
│   └── CsvIngestionService.php
└── Console/
    └── Commands/
        └── ProcessDailyBatches.php

database/
├── migrations/
└── factories/

routes/
├── api.php
└── web.php

tests/
├── Feature/
│   └── DispatchBatchTest.php
└── Unit/

config/
├── app.php
├── database.php
└── filesystems.php
```

## Performance Considerations

- **Batch Processing**: 500-2000 orders per batch (tested)
- **Database**: SQLite for development, MySQL for production
- **External APIs**: Each order makes 4 API calls (geocoding, holidays x2, weather, currency)
- **CSV Processing**: ~100ms per order with I/O
- **Caching**: Consider caching holiday data and weather forecasts

## Notes

- The service processes orders placed in the previous 24 hours
- Scheduled to run daily at 06:00 IST (configured in `bootstrap/app.php`)
- Grouped by city + dispatch_date for naive dispatch planning
- No vehicle routing or driver assignment (those services are separate)
- Error handling: Failed orders don't block batch processing
