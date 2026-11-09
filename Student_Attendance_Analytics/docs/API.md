# API v1 — for the teacher Android app

Base address: `http://<server>:8000/api/v1`
(From the Android emulator, the computer's `127.0.0.1` is `http://10.0.2.2:8000`.
Start the server with `php artisan serve --host=0.0.0.0 --port=8000` so the emulator or a phone can reach it.)

Every request sends:

```
Accept: application/json
Accept-Language: en        (or km for Khmer messages)
Authorization: Bearer <token>     (all calls except /login)
```

The rules are the same as on the website (`app/Services/AttendanceRecorder.php`):
a teacher records only their own class and subject, only for today; can change a lesson for 24 hours;
after that, a change becomes a request for the admin.

## Answers

| Code | Meaning | What the app does |
|---|---|---|
| 200 / 201 | OK / created | show the result |
| 202 | request sent, waiting for the admin | show "Request sent" |
| 401 | no token or token expired | go to the Login screen |
| 403 | not allowed (`message` says why) | show the message |
| 422 | form error: `{"message": "...", "errors": {"field": ["..."]}}` | show the message under the field |
| 429 | too many login tries | "Try again in a minute" |

## Login and profile

**POST `/login`**: body `{"login": "012 345 678", "password": "password", "device_name": "Samsung A15"}`
`login` is a phone (spaces allowed) or an email. Only teachers can log in (admin → 403).

```json
{ "token": "1|bkPz...", "user": { "id": 2, "name": "Pen Sovann", "name_km": "ប៉ែន សុវណ្ណ", "email": "sovann@school.edu.kh",
  "phone": "012345678", "role": "teacher", "locale": "en", "homeroom_class": { "id": 1, "name": "10A" } } }
```

Save the token (SharedPreferences) and send it on every call.

| Call | Body | Answer |
|---|---|---|
| **POST `/logout`** | — | `{"message": "Logged out."}` (this phone only) |
| **GET `/me`** | — | `{"data": user}` |
| **PUT `/me`** | `{"locale": "km"}` | `{"data": user}` |
| **PUT `/me/password`** | `{"current_password", "password", "password_confirmation"}` | `{"message": "Password changed."}`, other phones are logged out |

## Dashboard: today's lessons

**GET `/today`**

```json
{ "date": "2026-11-09", "done": 1, "total": 5, "lessons": [
  { "slot_id": 1, "period": 1, "start_time": "07:00", "end_time": "08:00",
    "class": { "id": 1, "name": "10A" },
    "subject": { "id": 2, "code": "MAT", "name_en": "Mathematics", "name_km": "គណិតវិទ្យា" },
    "students": 7, "status": "done", "session": { "id": 1, "present": 6, "absent": 1 } } ] }
```

`status` is `done` or `not_marked`; `session` is `null` when not marked.
"Up next" = the first lesson with `not_marked`.

## My Classes and Class details

**GET `/classes`**: `{"data": [{"id", "name", "grade", "room", "is_homeroom", "students", "attendance_rate", "subjects": [subject]}]}`

**GET `/classes/{id}/students`**:
`{"data": [{"id", "student_code", "name_latin", "name_km", "gender", "active", "attendance_rate", "risk", "parent": {"name", "phone", "relation", "channel"}}]}`
`attendance_rate` is `null` before any record; `risk` is `critical`, `high`, `medium`, `low` or `null`; `channel` is `telegram` or `sms`.

## Mark attendance

**GET `/attendance/roster?slot_id=1`** (a lesson from `/today`)
or `?school_class_id=1&subject_id=2&date=2026-11-09&period=1` (another class today)

```json
{ "lesson": { "class": {...}, "subject": {...}, "date": "2026-11-09", "period": 1, "start_time": "07:00", "end_time": "08:00" },
  "session_id": null, "already_taken": false, "needs_reason": false,
  "absence_reasons": ["not_given", "sick", "family_matter", "school_activity", "other"],
  "students": [ { "id": 5, "student_code": "S-10A-001", "name_latin": "Sok Dara", "name_km": "សុខ ដារា", "gender": "male",
                  "active": true, "status": "present", "absence_reason": null } ] }
```

Everyone starts with the saved status (or `present`). When `already_taken` is true, show "Edit".

**POST `/attendance`**: send only the absent students (everyone else is present):

```json
{ "school_class_id": 1, "subject_id": 2, "date": "2026-11-09", "period": 1,
  "records": [ { "student_id": 6, "status": "absent", "absence_reason": "sick" } ] }
```

Answer `201` (first time) or `200` (changed):
`{"message": "Attendance submitted", "session_id": 1, "present": 6, "absent": 1, "absent_students": [{"id", "name_latin", "name_km", "absence_reason"}]}`
→ the Success screen.

## Attendance History

**GET `/history?date=&class_id=&subject_id=&status=absent&q=&page=1`**: 30 per page.

```json
{ "data": [ { "id": 12, "status": "absent", "absence_reason": "sick", "date": "2026-11-09", "period": 1,
              "class": {...}, "subject": {...}, "student": {...},
              "can_change_directly": true, "has_pending_request": false } ],
  "links": {...}, "meta": { "current_page": 1, "last_page": 1, "total": 7 } }
```

**POST `/records/{id}/correct`**: `{"new_status": "present", "reason": "Arrived after roll call"}`

- within 24 hours → `200`, `"correction": {"status": "applied", ...}`, record changed
- after 24 hours → `202`, `"correction": {"status": "pending", ...}`, `reason` is required, the admin decides on the website
- `has_pending_request: true` → show "Request pending" instead of the button
