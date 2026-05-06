# Dispatch Planner

A backend service for planning dispatches in a B2B/B2C supply chain company. It ingests orders, computes dispatch plans based on working days, holidays, and weather, and outputs grouped runs.

## Setup

### Prerequisites
- Docker and Docker Compose
- Terraform

### Environment Setup
1. Clone the repository.
2. Run `docker compose up --build` to start the services (app, MySQL, LocalStack).
3. In a separate shell, run `cd terraform && terraform init && terraform apply -auto-approve` to create S3 buckets in LocalStack.
4. The app will be available at http://localhost:8000.

### Configuration
- Database: MySQL in Docker.
- S3: LocalStack at http://localhost:4566.
- S3 input bucket: `dispatch-input`
- S3 output bucket: `dispatch-output`
- External APIs: Open-Meteo, Nager.Date, Frankfurter (no auth required).
- Local fallback: CSV input and output fall back to local `storage/app` when S3 is unavailable.

## Usage

### API Endpoints
- `POST /api/dispatch-batches`: Submit a batch of orders as JSON.
  ```json
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
- `GET /api/dispatch-batches/{batch_id}`: Get the dispatch plan.
- `POST /api/dispatch-batches/{batch_id}/recompute`: Recompute the plan.
- `GET /api/healthz`: Health check.

### CSV Ingestion
Upload CSV files to the `dispatch-input` S3 bucket under the `input/` prefix. The service polls for new CSVs daily at 06:00 IST and processes them.

Example upload using AWS CLI:
```bash
aws --endpoint-url=http://localhost:4566 s3 cp orders.csv s3://dispatch-input/input/orders.csv
```

Run ingestion manually:
```bash
php artisan dispatch:process-daily
```

### Output
- JSON via API.
- CSV written to `dispatch-output` S3 bucket under the `output/` prefix.
- If S3 is unavailable, output falls back to local storage at `storage/app/output`.

## Design

### Data Model
- `dispatch_batches`: Batches of orders.
- `orders`: Individual orders with geocoded data, dispatch dates, etc.
- `dispatch_runs`: Grouped runs by city and date.
- Pivot table `order_dispatch_run`.

### External Integrations
- **Geocoding**: Nominatim (OpenStreetMap) for address to city/lat/lon.
- **Holidays**: Nager.Date for country-specific holidays.
- **Weather**: Open-Meteo for precipitation and temperature forecasts.
- **Currency**: Frankfurter for historical exchange rates.

### Trade-offs
- Geocoding uses free Nominatim; may have rate limits or inaccuracies.
- Weather checks only for the dispatch date; assumes forecast accuracy.
- Naive grouping by city; no optimization for vehicle routing.
- Error handling: Orders fail individually if geocoding fails, but batch continues.
- No authentication; assumes internal use.

## Tests
Run `php artisan test` to execute tests, including failure scenarios for external API failures.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
