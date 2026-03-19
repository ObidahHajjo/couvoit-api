# Resend trip templates

Add these template IDs to `.env` and make sure `config/services.php` can read them:

- `RESEND_TRIP_RESERVATION_PASSENGER_TEMPLATE_ID`
- `RESEND_TRIP_RESERVATION_DRIVER_TEMPLATE_ID`
- `RESEND_TRIP_RESERVATION_CANCEL_PASSENGER_TEMPLATE_ID`
- `RESEND_TRIP_RESERVATION_CANCEL_DRIVER_TEMPLATE_ID`
- `RESEND_TRIP_CANCELLED_PASSENGER_TEMPLATE_ID`

Each template can use these variables:

- `APP_NAME`
- `CURRENT_YEAR`
- `RECIPIENT_NAME`
- `HEADLINE`
- `INTRO`
- `ROUTE`
- `DEPARTURE_TIME`
- `ARRIVAL_TIME`
- `DRIVER_NAME`
- `OTHER_PERSON_LABEL`
- `OTHER_PERSON_NAME`
- `SUPPORT_EMAIL`

Recommended structure for every template:

- Greeting with `RECIPIENT_NAME`
- Main title with `HEADLINE`
- Intro text with `INTRO`
- Trip details block with `ROUTE`, `DEPARTURE_TIME`, `ARRIVAL_TIME`, `DRIVER_NAME`
- Optional related-user line with `OTHER_PERSON_LABEL` and `OTHER_PERSON_NAME`
- Footer with `APP_NAME`, `SUPPORT_EMAIL`, `CURRENT_YEAR`
