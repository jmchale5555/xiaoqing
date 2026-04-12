<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Request;
use Core\Session;
use Model\Booking;
use Model\BookingEvent;
use Model\DiningTable;
use Model\User;
use Throwable;

defined('ROOTPATH') or exit('Access Denied');

class BookingsController extends ApiController
{
    private const OVERSIZED_WARNING_SEAT_GAP = 4;

    /**
     * Endpoints:
     * - GET /api/bookings
     * - GET /api/bookings/show/{id}
     * - POST /api/bookings/create
     * - POST /api/bookings/update/{id}
     * - POST /api/bookings/cancel/{id}
     * - POST /api/bookings/assign_table/{id}
     * - GET /api/bookings/availability
     */
    public function index(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $booking = new Booking();
        $clauses = [];
        $params = [];

        $status = trim((string)($_GET['status'] ?? ''));
        if ($status !== '' && in_array($status, Booking::validStatuses(), true))
        {
            $clauses[] = 'status = :status';
            $params['status'] = $status;
        }

        $tableId = (int)($_GET['table_id'] ?? 0);
        if ($tableId > 0)
        {
            $clauses[] = 'table_id = :table_id';
            $params['table_id'] = $tableId;
        }

        $date = trim((string)($_GET['date'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
        {
            $clauses[] = 'booking_start >= :day_start AND booking_start < :day_end';
            $params['day_start'] = $date . ' 00:00:00';
            $params['day_end'] = date('Y-m-d H:i:s', strtotime($date . ' +1 day'));
        }

        $sql = 'SELECT * FROM bookings';
        if (!empty($clauses))
        {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY booking_start ASC, id ASC';

        try
        {
            $rows = $booking->query($sql, $params);
        }
        catch (Throwable $e)
        {
            $this->error('Bookings service unavailable', 500);
            return;
        }

        if (!is_array($rows))
        {
            $rows = [];
        }

        $this->ok([
            'bookings' => array_map([$this, 'formatBooking'], $rows),
        ]);
    }

    public function show(string $id = ''): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $bookingId = (int)$id;
        if ($bookingId <= 0)
        {
            $this->validationError(['id' => 'Invalid booking id']);
            return;
        }

        $booking = new Booking();

        try
        {
            $row = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Bookings service unavailable', 500);
            return;
        }

        if (!$row)
        {
            $this->notFound('Booking not found');
            return;
        }

        $this->ok([
            'booking' => $this->formatBooking($row),
            'events' => $this->listBookingEvents($bookingId),
        ]);
    }

    public function create(): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireStaffUser();
        if (!$sessionUser)
        {
            return;
        }

        [$data, $errors] = $this->validateCreatePayload($payload);
        if (!empty($errors))
        {
            $this->validationError($errors);
            return;
        }

        if (($data['table_id'] ?? null) !== null)
        {
            $assignment = $this->validateTableAssignment(
                (int)$data['table_id'],
                (int)$data['party_size'],
                (string)$data['booking_start'],
                (string)$data['booking_end'],
                0,
                (string)$data['status'],
                $this->toBool($payload['confirm_oversized'] ?? false),
                true
            );

            if (!empty($assignment['errors']))
            {
                $this->respondWithAssignmentErrors($assignment);
                return;
            }
        }

        $booking = new Booking();

        try
        {
            $booking->insert($data);
            $createdRows = $booking->where(['guest_name' => $data['guest_name']], [], [], 1, 0, 'id', 'desc', ['id']);
            $created = is_array($createdRows) && isset($createdRows[0]) ? $createdRows[0] : null;
        }
        catch (Throwable $e)
        {
            $this->error('Unable to create booking', 500);
            return;
        }

        if (!$created)
        {
            $this->error('Unable to create booking', 500);
            return;
        }

        $this->recordBookingEvent(
            (int)($created->id ?? 0),
            'booking_created',
            null,
            null,
            [
                'status' => (string)($created->status ?? Booking::STATUS_PENDING),
                'table_id' => ($created->table_id ?? null) !== null ? (int)$created->table_id : null,
                'party_size' => (int)($created->party_size ?? 0),
            ],
            (int)($sessionUser->id ?? 0)
        );

        $this->ok(['booking' => $this->formatBooking($created)], 201);
    }

    public function update(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $bookingId = (int)$id;
        if ($bookingId <= 0)
        {
            $this->validationError(['id' => 'Invalid booking id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireStaffUser();
        if (!$sessionUser)
        {
            return;
        }

        $booking = new Booking();

        try
        {
            $existing = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Bookings service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Booking not found');
            return;
        }

        [$data, $errors] = $this->validateUpdatePayload($payload, $existing);
        if (!empty($errors))
        {
            $this->validationError($errors);
            return;
        }

        if (empty($data))
        {
            $this->validationError(['payload' => 'No updatable fields provided']);
            return;
        }

        $nextPartySize = array_key_exists('party_size', $data) ? (int)$data['party_size'] : (int)$existing->party_size;
        $nextStart = array_key_exists('booking_start', $data) ? (string)$data['booking_start'] : (string)$existing->booking_start;
        $nextEnd = array_key_exists('booking_end', $data) ? (string)$data['booking_end'] : (string)$existing->booking_end;
        $nextTableId = array_key_exists('table_id', $data) ? ($data['table_id'] === null ? null : (int)$data['table_id']) : (($existing->table_id ?? null) !== null ? (int)$existing->table_id : null);
        $nextStatus = array_key_exists('status', $data) ? (string)$data['status'] : (string)$existing->status;

        if ($nextTableId !== null)
        {
            $requiresOversizedConfirmation =
                array_key_exists('table_id', $payload) ||
                array_key_exists('party_size', $payload);

            $assignment = $this->validateTableAssignment(
                $nextTableId,
                $nextPartySize,
                $nextStart,
                $nextEnd,
                $bookingId,
                $nextStatus,
                $this->toBool($payload['confirm_oversized'] ?? false),
                $requiresOversizedConfirmation
            );

            if (!empty($assignment['errors']))
            {
                $this->respondWithAssignmentErrors($assignment);
                return;
            }
        }

        try
        {
            $booking->update($bookingId, $data);
            $updated = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to update booking', 500);
            return;
        }

        if (!$updated)
        {
            $this->error('Unable to update booking', 500);
            return;
        }

        $this->recordBookingUpdateEvents($existing, $updated, (int)($sessionUser->id ?? 0));

        $this->ok(['booking' => $this->formatBooking($updated)]);
    }

    public function cancel(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $bookingId = (int)$id;
        if ($bookingId <= 0)
        {
            $this->validationError(['id' => 'Invalid booking id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireStaffUser();
        if (!$sessionUser)
        {
            return;
        }

        $booking = new Booking();

        try
        {
            $existing = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Bookings service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Booking not found');
            return;
        }

        if (!$this->isAllowedStatusTransition((string)$existing->status, Booking::STATUS_CANCELLED))
        {
            $this->validationError([
                'status' => 'Invalid status transition from ' . (string)$existing->status . ' to ' . Booking::STATUS_CANCELLED,
            ]);
            return;
        }

        try
        {
            $booking->update($bookingId, ['status' => Booking::STATUS_CANCELLED]);
            $updated = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to cancel booking', 500);
            return;
        }

        $this->recordBookingEvent(
            $bookingId,
            'booking_cancelled',
            (string)($existing->status ?? ''),
            Booking::STATUS_CANCELLED,
            null,
            (int)($sessionUser->id ?? 0)
        );

        $this->ok(['booking' => $this->formatBooking($updated)]);
    }

    public function assign_table(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $bookingId = (int)$id;
        if ($bookingId <= 0)
        {
            $this->validationError(['id' => 'Invalid booking id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireStaffUser();
        if (!$sessionUser)
        {
            return;
        }

        $tableId = (int)($payload['table_id'] ?? 0);
        if ($tableId <= 0)
        {
            $this->validationError(['table_id' => 'table_id must be a positive integer']);
            return;
        }

        $booking = new Booking();

        try
        {
            $existing = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Bookings service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Booking not found');
            return;
        }

        $assignment = $this->validateTableAssignment(
            $tableId,
            (int)$existing->party_size,
            (string)$existing->booking_start,
            (string)$existing->booking_end,
            $bookingId,
            (string)$existing->status,
            $this->toBool($payload['confirm_oversized'] ?? false),
            true
        );

        if (!empty($assignment['errors']))
        {
            $this->respondWithAssignmentErrors($assignment);
            return;
        }

        try
        {
            $booking->update($bookingId, ['table_id' => $tableId]);
            $updated = $booking->first(['id' => $bookingId]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to assign table', 500);
            return;
        }

        $fromTableId = ($existing->table_id ?? null) !== null ? (int)$existing->table_id : null;
        $eventType = $fromTableId === null ? 'booking_table_assigned' : 'booking_table_reassigned';
        $this->recordBookingEvent(
            $bookingId,
            $eventType,
            $fromTableId,
            $tableId,
            null,
            (int)($sessionUser->id ?? 0)
        );

        $this->ok(['booking' => $this->formatBooking($updated)]);
    }

    public function availability(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $partySize = (int)($_GET['party_size'] ?? 0);
        $start = $this->normalizeDateTime($_GET['booking_start'] ?? '');
        $end = $this->normalizeDateTime($_GET['booking_end'] ?? '');

        if ($partySize < 1)
        {
            $this->validationError(['party_size' => 'party_size must be a positive integer']);
            return;
        }

        if ($start === null)
        {
            $this->validationError(['booking_start' => 'booking_start is required and must be a valid datetime']);
            return;
        }

        if ($end === null)
        {
            $duration = (int)($_GET['duration_minutes'] ?? 120);
            if ($duration < 15)
            {
                $duration = 15;
            }
            $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $duration . ' minutes'));
        }

        if (strtotime($end) <= strtotime($start))
        {
            $this->validationError(['booking_end' => 'booking_end must be after booking_start']);
            return;
        }

        $excludeBookingId = (int)($_GET['exclude_booking_id'] ?? 0);
        $tablesModel = new DiningTable();

        try
        {
            $tables = $tablesModel->where(
                ['is_active' => 1],
                [],
                ['seats' => max(0, $partySize - 1)],
                1000,
                0,
                'display_order',
                'asc',
                ['display_order', 'id']
            );
        }
        catch (Throwable $e)
        {
            $this->error('Tables service unavailable', 500);
            return;
        }

        if (!is_array($tables))
        {
            $tables = [];
        }

        $recommended = [];
        $larger = [];
        $busy = [];

        foreach ($tables as $table)
        {
            $tableId = (int)($table->id ?? 0);
            if ($tableId <= 0)
            {
                continue;
            }

            if ($this->hasTableConflict($tableId, $start, $end, $excludeBookingId))
            {
                $busy[] = $tableId;
                continue;
            }

            $formatted = $this->formatTable($table);
            $extraSeats = max(0, ((int)($table->seats ?? 0)) - $partySize);

            if ($extraSeats >= self::OVERSIZED_WARNING_SEAT_GAP)
            {
                $larger[] = array_merge($formatted, ['extra_seats' => $extraSeats]);
                continue;
            }

            $recommended[] = array_merge($formatted, ['extra_seats' => $extraSeats]);
        }

        $available = array_merge($recommended, $larger);

        $this->ok([
            'available_tables' => $available,
            'recommended_tables' => $recommended,
            'larger_tables' => $larger,
            'busy_table_ids' => $busy,
            'query' => [
                'party_size' => $partySize,
                'booking_start' => $start,
                'booking_end' => $end,
                'oversized_warning_seat_gap' => self::OVERSIZED_WARNING_SEAT_GAP,
            ],
        ]);
    }

    private function validateCreatePayload(array $payload): array
    {
        $errors = [];
        $data = [];

        $guestName = trim((string)($payload['guest_name'] ?? ''));
        if ($guestName === '')
        {
            $errors['guest_name'] = 'guest_name is required';
        }
        elseif (mb_strlen($guestName) > 160)
        {
            $errors['guest_name'] = 'guest_name must be 160 characters or less';
        }
        else
        {
            $data['guest_name'] = $guestName;
        }

        $guestPhone = trim((string)($payload['guest_phone'] ?? ''));
        if (mb_strlen($guestPhone) > 40)
        {
            $errors['guest_phone'] = 'guest_phone must be 40 characters or less';
        }
        else
        {
            $data['guest_phone'] = $guestPhone !== '' ? $guestPhone : null;
        }

        $guestEmail = trim((string)($payload['guest_email'] ?? ''));
        if ($guestEmail !== '' && (mb_strlen($guestEmail) > 180 || filter_var($guestEmail, FILTER_VALIDATE_EMAIL) === false))
        {
            $errors['guest_email'] = 'guest_email must be a valid email address';
        }
        else
        {
            $data['guest_email'] = $guestEmail !== '' ? $guestEmail : null;
        }

        $partySize = $payload['party_size'] ?? null;
        if ($partySize === null || $partySize === '')
        {
            $errors['party_size'] = 'party_size is required';
        }
        elseif (filter_var($partySize, FILTER_VALIDATE_INT) === false || (int)$partySize < 1)
        {
            $errors['party_size'] = 'party_size must be a positive integer';
        }
        else
        {
            $data['party_size'] = (int)$partySize;
        }

        $start = $this->normalizeDateTime($payload['booking_start'] ?? '');
        if ($start === null)
        {
            $errors['booking_start'] = 'booking_start is required and must be a valid datetime';
        }

        $end = $this->normalizeDateTime($payload['booking_end'] ?? '');
        if ($start !== null && $end === null)
        {
            $duration = (int)($payload['duration_minutes'] ?? 120);
            if ($duration < 15)
            {
                $duration = 15;
            }
            $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $duration . ' minutes'));
        }

        if ($start !== null && $end !== null && strtotime($end) <= strtotime($start))
        {
            $errors['booking_end'] = 'booking_end must be after booking_start';
        }

        if ($start !== null)
        {
            $data['booking_start'] = $start;
        }

        if ($end !== null)
        {
            $data['booking_end'] = $end;
        }

        $status = trim((string)($payload['status'] ?? Booking::STATUS_PENDING));
        if (!in_array($status, Booking::validStatuses(), true))
        {
            $errors['status'] = 'Invalid booking status';
        }
        else
        {
            $data['status'] = $status;
        }

        $tableId = $payload['table_id'] ?? null;
        if ($tableId === null || $tableId === '')
        {
            $data['table_id'] = null;
        }
        elseif (filter_var($tableId, FILTER_VALIDATE_INT) === false || (int)$tableId <= 0)
        {
            $errors['table_id'] = 'table_id must be a positive integer';
        }
        else
        {
            $data['table_id'] = (int)$tableId;
        }

        $notes = trim((string)($payload['notes'] ?? ''));
        $data['notes'] = $notes !== '' ? $notes : null;

        return [$data, $errors];
    }

    private function validateUpdatePayload(array $payload, mixed $existing): array
    {
        $errors = [];
        $data = [];

        if (array_key_exists('guest_name', $payload))
        {
            $guestName = trim((string)$payload['guest_name']);
            if ($guestName === '')
            {
                $errors['guest_name'] = 'guest_name is required';
            }
            elseif (mb_strlen($guestName) > 160)
            {
                $errors['guest_name'] = 'guest_name must be 160 characters or less';
            }
            else
            {
                $data['guest_name'] = $guestName;
            }
        }

        if (array_key_exists('guest_phone', $payload))
        {
            $guestPhone = trim((string)$payload['guest_phone']);
            if (mb_strlen($guestPhone) > 40)
            {
                $errors['guest_phone'] = 'guest_phone must be 40 characters or less';
            }
            else
            {
                $data['guest_phone'] = $guestPhone !== '' ? $guestPhone : null;
            }
        }

        if (array_key_exists('guest_email', $payload))
        {
            $guestEmail = trim((string)$payload['guest_email']);
            if ($guestEmail !== '' && (mb_strlen($guestEmail) > 180 || filter_var($guestEmail, FILTER_VALIDATE_EMAIL) === false))
            {
                $errors['guest_email'] = 'guest_email must be a valid email address';
            }
            else
            {
                $data['guest_email'] = $guestEmail !== '' ? $guestEmail : null;
            }
        }

        if (array_key_exists('party_size', $payload))
        {
            if (filter_var($payload['party_size'], FILTER_VALIDATE_INT) === false || (int)$payload['party_size'] < 1)
            {
                $errors['party_size'] = 'party_size must be a positive integer';
            }
            else
            {
                $data['party_size'] = (int)$payload['party_size'];
            }
        }

        $start = array_key_exists('booking_start', $payload)
            ? $this->normalizeDateTime($payload['booking_start'])
            : (string)$existing->booking_start;
        $end = array_key_exists('booking_end', $payload)
            ? $this->normalizeDateTime($payload['booking_end'])
            : (string)$existing->booking_end;

        if (array_key_exists('booking_start', $payload) && $start === null)
        {
            $errors['booking_start'] = 'booking_start must be a valid datetime';
        }

        if (array_key_exists('booking_end', $payload) && $end === null)
        {
            $errors['booking_end'] = 'booking_end must be a valid datetime';
        }

        if ($start !== null && $end !== null && strtotime($end) <= strtotime($start))
        {
            $errors['booking_end'] = 'booking_end must be after booking_start';
        }

        if (array_key_exists('booking_start', $payload) && $start !== null)
        {
            $data['booking_start'] = $start;
        }

        if (array_key_exists('booking_end', $payload) && $end !== null)
        {
            $data['booking_end'] = $end;
        }

        if (array_key_exists('status', $payload))
        {
            $status = trim((string)$payload['status']);
            if (!in_array($status, Booking::validStatuses(), true))
            {
                $errors['status'] = 'Invalid booking status';
            }
            elseif (!$this->isAllowedStatusTransition((string)$existing->status, $status))
            {
                $errors['status'] = 'Invalid status transition from ' . (string)$existing->status . ' to ' . $status;
            }
            else
            {
                $data['status'] = $status;
            }
        }

        if (array_key_exists('table_id', $payload))
        {
            if ($payload['table_id'] === null || $payload['table_id'] === '')
            {
                $data['table_id'] = null;
            }
            elseif (filter_var($payload['table_id'], FILTER_VALIDATE_INT) === false || (int)$payload['table_id'] <= 0)
            {
                $errors['table_id'] = 'table_id must be a positive integer';
            }
            else
            {
                $data['table_id'] = (int)$payload['table_id'];
            }
        }

        if (array_key_exists('notes', $payload))
        {
            $notes = trim((string)$payload['notes']);
            $data['notes'] = $notes !== '' ? $notes : null;
        }

        return [$data, $errors];
    }

    private function validateTableAssignment(
        int $tableId,
        int $partySize,
        string $start,
        string $end,
        int $ignoreBookingId,
        string $bookingStatus,
        bool $confirmOversized,
        bool $enforceOversizedConfirmation
    ): array {
        $errors = [];
        $warning = null;

        $tables = new DiningTable();
        try
        {
            $table = $tables->first(['id' => $tableId]);
        }
        catch (Throwable $e)
        {
            return [
                'errors' => ['table_id' => 'Tables service unavailable'],
                'warning' => null,
            ];
        }

        if (!$table)
        {
            return [
                'errors' => ['table_id' => 'Table does not exist'],
                'warning' => null,
            ];
        }

        if (empty($table->is_active))
        {
            return [
                'errors' => ['table_id' => 'Table is not active'],
                'warning' => null,
            ];
        }

        if ((int)($table->seats ?? 0) < $partySize)
        {
            return [
                'errors' => ['table_id' => 'Table does not have enough seats'],
                'warning' => null,
            ];
        }

        $seats = (int)($table->seats ?? 0);
        $extraSeats = max(0, $seats - $partySize);
        if ($extraSeats >= self::OVERSIZED_WARNING_SEAT_GAP)
        {
            $warning = [
                'code' => 'oversized_table_confirmation_required',
                'message' => 'Selected table has 4 or more extra seats. Staff confirmation is required.',
                'table_id' => (int)($table->id ?? 0),
                'table_name' => (string)($table->name ?? ''),
                'party_size' => $partySize,
                'seats' => $seats,
                'extra_seats' => $extraSeats,
                'seat_gap_threshold' => self::OVERSIZED_WARNING_SEAT_GAP,
            ];

            if ($enforceOversizedConfirmation && !$confirmOversized)
            {
                $errors['confirm_oversized'] = 'Set confirm_oversized=true to assign an oversized table';
            }
        }

        if ($this->statusBlocksAvailability($bookingStatus) && $this->hasTableConflict($tableId, $start, $end, $ignoreBookingId))
        {
            $errors['table_id'] = 'Table is not available for the selected time window';
        }

        return [
            'errors' => $errors,
            'warning' => $warning,
        ];
    }

    private function respondWithAssignmentErrors(array $assignment): void
    {
        $payload = [
            'message' => 'Validation failed',
            'errors' => $assignment['errors'] ?? [],
        ];

        if (!empty($assignment['warning']) && is_array($assignment['warning']))
        {
            $payload['warning'] = $assignment['warning'];
        }

        $this->json($payload, 422);
    }

    private function hasTableConflict(int $tableId, string $start, string $end, int $ignoreBookingId = 0): bool
    {
        $booking = new Booking();

        $sql = 'SELECT id FROM bookings WHERE table_id = :table_id '
            . 'AND booking_start < :booking_end '
            . 'AND booking_end > :booking_start '
            . 'AND status IN (:pending, :confirmed, :seated)';

        $params = [
            'table_id' => $tableId,
            'booking_start' => $start,
            'booking_end' => $end,
            'pending' => Booking::STATUS_PENDING,
            'confirmed' => Booking::STATUS_CONFIRMED,
            'seated' => Booking::STATUS_SEATED,
        ];

        if ($ignoreBookingId > 0)
        {
            $sql .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreBookingId;
        }

        $sql .= ' LIMIT 1';

        try
        {
            $rows = $booking->query($sql, $params);
        }
        catch (Throwable $e)
        {
            return true;
        }

        return is_array($rows) && !empty($rows);
    }

    private function statusBlocksAvailability(string $status): bool
    {
        return in_array($status, [
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_SEATED,
        ], true);
    }

    private function isAllowedStatusTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus)
        {
            return true;
        }

        $allowedTransitions = [
            Booking::STATUS_PENDING => [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_SEATED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW,
            ],
            Booking::STATUS_CONFIRMED => [
                Booking::STATUS_SEATED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_NO_SHOW,
            ],
            Booking::STATUS_SEATED => [
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
            ],
            Booking::STATUS_COMPLETED => [],
            Booking::STATUS_CANCELLED => [],
            Booking::STATUS_NO_SHOW => [],
        ];

        $allowed = $allowedTransitions[$fromStatus] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '')
        {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false)
        {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function formatBooking(mixed $row): array
    {
        return [
            'id' => isset($row->id) ? (int)$row->id : null,
            'guest_name' => (string)($row->guest_name ?? ''),
            'guest_phone' => $row->guest_phone ?? null,
            'guest_email' => $row->guest_email ?? null,
            'party_size' => isset($row->party_size) ? (int)$row->party_size : 0,
            'booking_start' => $row->booking_start ?? null,
            'booking_end' => $row->booking_end ?? null,
            'status' => (string)($row->status ?? Booking::STATUS_PENDING),
            'table_id' => ($row->table_id ?? null) !== null ? (int)$row->table_id : null,
            'notes' => $row->notes ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function formatTable(mixed $row): array
    {
        return [
            'id' => isset($row->id) ? (int)$row->id : null,
            'name' => (string)($row->name ?? ''),
            'seats' => isset($row->seats) ? (int)$row->seats : 0,
            'is_active' => !empty($row->is_active),
            'display_order' => isset($row->display_order) ? (int)$row->display_order : 0,
        ];
    }

    private function listBookingEvents(int $bookingId): array
    {
        if ($bookingId <= 0)
        {
            return [];
        }

        $events = new BookingEvent();

        try
        {
            $rows = $events->where(['booking_id' => $bookingId], [], [], 200, 0, 'id', 'desc', ['id', 'created_at']);
        }
        catch (Throwable $e)
        {
            return [];
        }

        if (!is_array($rows))
        {
            return [];
        }

        return array_map([$this, 'formatBookingEvent'], $rows);
    }

    private function formatBookingEvent(mixed $row): array
    {
        $meta = null;
        $rawMeta = $row->meta_json ?? null;
        if (is_string($rawMeta) && $rawMeta !== '')
        {
            $decoded = json_decode($rawMeta, true);
            if (is_array($decoded))
            {
                $meta = $decoded;
            }
        }

        return [
            'id' => isset($row->id) ? (int)$row->id : null,
            'booking_id' => isset($row->booking_id) ? (int)$row->booking_id : null,
            'event_type' => (string)($row->event_type ?? ''),
            'actor_user_id' => ($row->actor_user_id ?? null) !== null ? (int)$row->actor_user_id : null,
            'from_value' => $row->from_value ?? null,
            'to_value' => $row->to_value ?? null,
            'meta' => $meta,
            'created_at' => $row->created_at ?? null,
        ];
    }

    private function recordBookingEvent(
        int $bookingId,
        string $eventType,
        mixed $fromValue,
        mixed $toValue,
        ?array $meta,
        int $actorUserId
    ): void {
        if ($bookingId <= 0 || $eventType === '')
        {
            return;
        }

        $events = new BookingEvent();
        $payload = [
            'booking_id' => $bookingId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'from_value' => $this->stringifyAuditValue($fromValue),
            'to_value' => $this->stringifyAuditValue($toValue),
            'meta_json' => $meta !== null ? json_encode($meta) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        try
        {
            $events->insert($payload);
        }
        catch (Throwable $e)
        {
            // Audit failures should not block operational booking actions.
        }
    }

    private function recordBookingUpdateEvents(mixed $before, mixed $after, int $actorUserId): void
    {
        $bookingId = (int)($after->id ?? $before->id ?? 0);
        if ($bookingId <= 0)
        {
            return;
        }

        $beforeStatus = (string)($before->status ?? Booking::STATUS_PENDING);
        $afterStatus = (string)($after->status ?? Booking::STATUS_PENDING);
        if ($beforeStatus !== $afterStatus)
        {
            $this->recordBookingEvent($bookingId, 'booking_status_changed', $beforeStatus, $afterStatus, null, $actorUserId);
        }

        $beforeTableId = ($before->table_id ?? null) !== null ? (int)$before->table_id : null;
        $afterTableId = ($after->table_id ?? null) !== null ? (int)$after->table_id : null;
        if ($beforeTableId !== $afterTableId)
        {
            $eventType = $beforeTableId === null ? 'booking_table_assigned' : 'booking_table_reassigned';
            if ($afterTableId === null)
            {
                $eventType = 'booking_table_unassigned';
            }

            $this->recordBookingEvent($bookingId, $eventType, $beforeTableId, $afterTableId, null, $actorUserId);
        }

        $changedFields = [];
        $watchFields = [
            'guest_name',
            'guest_phone',
            'guest_email',
            'party_size',
            'booking_start',
            'booking_end',
            'notes',
        ];

        foreach ($watchFields as $field)
        {
            $beforeValue = $this->stringifyAuditValue($before->{$field} ?? null);
            $afterValue = $this->stringifyAuditValue($after->{$field} ?? null);
            if ($beforeValue !== $afterValue)
            {
                $changedFields[] = $field;
            }
        }

        if (!empty($changedFields))
        {
            $this->recordBookingEvent(
                $bookingId,
                'booking_details_updated',
                null,
                null,
                ['changed_fields' => $changedFields],
                $actorUserId
            );
        }
    }

    private function stringifyAuditValue(mixed $value): ?string
    {
        if ($value === null)
        {
            return null;
        }

        if (is_bool($value))
        {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value))
        {
            return (string)$value;
        }

        return json_encode($value);
    }

    private function requireWriteMethod(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST' && $method !== 'PUT' && $method !== 'PATCH' && $method !== 'DELETE')
        {
            $this->methodNotAllowed(['POST', 'PUT', 'PATCH', 'DELETE']);
            return false;
        }

        return true;
    }

    private function readPayload(): array
    {
        $request = new Request();
        $payload = $request->json();

        if (empty($payload))
        {
            $payload = $request->post();
        }

        return is_array($payload) ? $payload : [];
    }

    private function requireStaffUser(): mixed
    {
        $session = new Session();
        $user = $session->user();

        if (!$user)
        {
            $this->unauthenticated();
            return null;
        }

        $role = (string)($user->role ?? User::ROLE_CUSTOMER);
        if (!in_array($role, [User::ROLE_STAFF, User::ROLE_MANAGER], true))
        {
            $this->forbidden('Staff access required');
            return null;
        }

        return $user;
    }

    private function verifyCsrfToken(array $payload = []): bool
    {
        $session = new Session();
        $expected = (string)$session->get('csrf_token', '');

        if ($expected === '')
        {
            $this->error('Invalid CSRF token', 419);
            return false;
        }

        $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($provided === '')
        {
            $provided = (string)($payload['csrfToken'] ?? '');
        }

        if ($provided === '' || !hash_equals($expected, $provided))
        {
            $this->error('Invalid CSRF token', 419);
            return false;
        }

        return true;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value))
        {
            return $value;
        }

        if (is_int($value) || is_float($value))
        {
            return (int)$value === 1;
        }

        if (is_string($value))
        {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
