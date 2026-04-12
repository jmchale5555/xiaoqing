<?php

namespace Tests\Feature;

use Tests\Support\HttpClient;
use Tests\TestCase;

class BookingsApiTest extends TestCase
{
    private HttpClient $staff;

    public static function setUpBeforeClass(): void
    {
        self::resetTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = new HttpClient(self::baseUrl());
        $this->signupClient($this->staff, 'booking-staff@example.com');
    }

    public function testBookingsIndexReturnsArray(): void
    {
        $response = $this->staff->get('/api/bookings');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['json']['bookings'] ?? null);
    }

    public function testCreateShowUpdateCancelBookingFlow(): void
    {
        $token = $this->staff->csrfToken();
        $create = $this->staff->post('/api/bookings/create', [
            'guest_name' => 'Alice',
            'guest_phone' => '07123456789',
            'party_size' => 4,
            'booking_start' => '2026-03-25 18:00:00',
            'booking_end' => '2026-03-25 19:30:00',
            'notes' => 'Window seat',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $create['status']);
        $bookingId = (int)($create['json']['booking']['id'] ?? 0);
        $this->assertGreaterThan(0, $bookingId);

        $show = $this->staff->get('/api/bookings/show/' . $bookingId);
        $this->assertSame(200, $show['status']);
        $this->assertSame('Alice', $show['json']['booking']['guest_name'] ?? '');

        $updateToken = $this->staff->csrfToken();
        $update = $this->staff->post('/api/bookings/update/' . $bookingId, [
            'party_size' => 5,
            'status' => 'confirmed',
        ], ['X-CSRF-Token' => $updateToken]);

        $this->assertSame(200, $update['status']);
        $this->assertSame(5, (int)($update['json']['booking']['party_size'] ?? 0));
        $this->assertSame('confirmed', $update['json']['booking']['status'] ?? '');

        $cancelToken = $this->staff->csrfToken();
        $cancel = $this->staff->post('/api/bookings/cancel/' . $bookingId, [], ['X-CSRF-Token' => $cancelToken]);
        $this->assertSame(200, $cancel['status']);
        $this->assertSame('cancelled', $cancel['json']['booking']['status'] ?? '');
    }

    public function testCreateBookingValidatesPayload(): void
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/bookings/create', [
            'guest_name' => '',
            'party_size' => 0,
            'booking_start' => 'bad-date',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('guest_name', $response['json']['errors'] ?? []);
        $this->assertArrayHasKey('party_size', $response['json']['errors'] ?? []);
        $this->assertArrayHasKey('booking_start', $response['json']['errors'] ?? []);
    }

    public function testCreateBookingRequiresAuthenticationAndCsrf(): void
    {
        $guest = new HttpClient(self::baseUrl());

        $withoutAuth = $guest->post('/api/bookings/create', [
            'guest_name' => 'Guest',
            'party_size' => 2,
            'booking_start' => '2026-03-25 18:00:00',
        ]);

        $this->assertSame(419, $withoutAuth['status']);

        $csrf = $guest->csrfToken();
        $withCsrfNoAuth = $guest->post('/api/bookings/create', [
            'guest_name' => 'Guest',
            'party_size' => 2,
            'booking_start' => '2026-03-25 18:00:00',
        ], ['X-CSRF-Token' => $csrf]);

        $this->assertSame(401, $withCsrfNoAuth['status']);
    }

    public function testCreateBookingRequiresStaffRole(): void
    {
        $customer = $this->createCustomerClient('booking-customer-');

        $token = $customer->csrfToken();
        $response = $customer->post('/api/bookings/create', [
            'guest_name' => 'Customer Guest',
            'party_size' => 2,
            'booking_start' => '2026-03-25 18:00:00',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(403, $response['status']);
    }

    public function testBookingsUpdateCancelAndAssignRequireStaffRole(): void
    {
        $bookingId = $this->createBooking('Staff Booking', 4, '2026-03-26 18:00:00', '2026-03-26 19:30:00');
        $tableId = $this->createTable('Role Test Table', 4);
        $customer = $this->createCustomerClient('booking-customer-mutate-');

        $updateToken = $customer->csrfToken();
        $update = $customer->post('/api/bookings/update/' . $bookingId, [
            'notes' => 'Not allowed',
        ], ['X-CSRF-Token' => $updateToken]);
        $this->assertSame(403, $update['status']);

        $cancelToken = $customer->csrfToken();
        $cancel = $customer->post('/api/bookings/cancel/' . $bookingId, [], ['X-CSRF-Token' => $cancelToken]);
        $this->assertSame(403, $cancel['status']);

        $assignToken = $customer->csrfToken();
        $assign = $customer->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $tableId,
        ], ['X-CSRF-Token' => $assignToken]);
        $this->assertSame(403, $assign['status']);
    }

    public function testAssignTableRejectsInsufficientSeats(): void
    {
        $smallTableId = $this->createTable('Small Table', 2);
        $bookingId = $this->createBooking('Large Party', 4, '2026-03-25 18:00:00', '2026-03-25 19:30:00');

        $token = $this->staff->csrfToken();
        $assign = $this->staff->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $smallTableId,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $assign['status']);
        $this->assertArrayHasKey('table_id', $assign['json']['errors'] ?? []);
    }

    public function testAssignTableRejectsTimeConflicts(): void
    {
        $tableId = $this->createTable('Conflict Table', 4);

        $firstBookingId = $this->createBooking('First', 4, '2026-03-25 18:00:00', '2026-03-25 19:30:00');
        $assignToken = $this->staff->csrfToken();
        $firstAssign = $this->staff->post('/api/bookings/assign_table/' . $firstBookingId, [
            'table_id' => $tableId,
        ], ['X-CSRF-Token' => $assignToken]);
        $this->assertSame(200, $firstAssign['status']);

        $secondBookingId = $this->createBooking('Second', 4, '2026-03-25 18:30:00', '2026-03-25 20:00:00');
        $secondToken = $this->staff->csrfToken();
        $secondAssign = $this->staff->post('/api/bookings/assign_table/' . $secondBookingId, [
            'table_id' => $tableId,
        ], ['X-CSRF-Token' => $secondToken]);

        $this->assertSame(422, $secondAssign['status']);
        $this->assertArrayHasKey('table_id', $secondAssign['json']['errors'] ?? []);
    }

    public function testAssignTableRequiresOversizedConfirmation(): void
    {
        $largeTableId = $this->createTable('Large Table', 8);
        $bookingId = $this->createBooking('Couple', 2, '2026-03-27 18:00:00', '2026-03-27 19:30:00');

        $token = $this->staff->csrfToken();
        $assignWithoutConfirm = $this->staff->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $largeTableId,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $assignWithoutConfirm['status']);
        $this->assertArrayHasKey('confirm_oversized', $assignWithoutConfirm['json']['errors'] ?? []);
        $this->assertSame('oversized_table_confirmation_required', $assignWithoutConfirm['json']['warning']['code'] ?? null);

        $confirmToken = $this->staff->csrfToken();
        $assignWithConfirm = $this->staff->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $largeTableId,
            'confirm_oversized' => true,
        ], ['X-CSRF-Token' => $confirmToken]);

        $this->assertSame(200, $assignWithConfirm['status']);
        $this->assertSame($largeTableId, (int)($assignWithConfirm['json']['booking']['table_id'] ?? 0));
    }

    public function testCreateBookingWithOversizedTableRequiresConfirmation(): void
    {
        $largeTableId = $this->createTable('Create Large', 8);

        $token = $this->staff->csrfToken();
        $withoutConfirm = $this->staff->post('/api/bookings/create', [
            'guest_name' => 'Walk In',
            'party_size' => 2,
            'booking_start' => '2026-03-27 20:00:00',
            'booking_end' => '2026-03-27 21:00:00',
            'table_id' => $largeTableId,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $withoutConfirm['status']);
        $this->assertArrayHasKey('confirm_oversized', $withoutConfirm['json']['errors'] ?? []);

        $confirmToken = $this->staff->csrfToken();
        $withConfirm = $this->staff->post('/api/bookings/create', [
            'guest_name' => 'Walk In',
            'party_size' => 2,
            'booking_start' => '2026-03-27 20:00:00',
            'booking_end' => '2026-03-27 21:00:00',
            'table_id' => $largeTableId,
            'confirm_oversized' => true,
        ], ['X-CSRF-Token' => $confirmToken]);

        $this->assertSame(201, $withConfirm['status']);
        $this->assertSame($largeTableId, (int)($withConfirm['json']['booking']['table_id'] ?? 0));
    }

    public function testUpdateRejectsInvalidStatusTransition(): void
    {
        $bookingId = $this->createBooking('Transition Guest', 2, '2026-03-28 18:00:00', '2026-03-28 19:00:00');

        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/bookings/update/' . $bookingId, [
            'status' => 'completed',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('status', $response['json']['errors'] ?? []);
    }

    public function testCancelRejectsTerminalStatusTransition(): void
    {
        $token = $this->staff->csrfToken();
        $create = $this->staff->post('/api/bookings/create', [
            'guest_name' => 'Terminal Guest',
            'party_size' => 2,
            'booking_start' => '2026-03-28 20:00:00',
            'booking_end' => '2026-03-28 21:00:00',
            'status' => 'completed',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $create['status']);
        $bookingId = (int)($create['json']['booking']['id'] ?? 0);

        $cancelToken = $this->staff->csrfToken();
        $cancel = $this->staff->post('/api/bookings/cancel/' . $bookingId, [], ['X-CSRF-Token' => $cancelToken]);

        $this->assertSame(422, $cancel['status']);
        $this->assertArrayHasKey('status', $cancel['json']['errors'] ?? []);
    }

    public function testAvailabilityReturnsOnlyFreeSuitableTables(): void
    {
        $smallId = $this->createTable('Small', 2);
        $busyId = $this->createTable('Busy', 4);
        $freeId = $this->createTable('Free', 6);
        $largerId = $this->createTable('Larger', 9);

        $bookingId = $this->createBooking('Busy Party', 4, '2026-03-26 19:00:00', '2026-03-26 20:30:00');
        $assignToken = $this->staff->csrfToken();
        $assign = $this->staff->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $busyId,
        ], ['X-CSRF-Token' => $assignToken]);
        $this->assertSame(200, $assign['status']);

        $response = $this->staff->get('/api/bookings/availability?party_size=4&booking_start=2026-03-26%2019:15:00&booking_end=2026-03-26%2020:00:00');
        $this->assertSame(200, $response['status']);

        $available = array_map(fn ($table) => (int)($table['id'] ?? 0), $response['json']['available_tables'] ?? []);
        $recommended = array_map(fn ($table) => (int)($table['id'] ?? 0), $response['json']['recommended_tables'] ?? []);
        $larger = array_map(fn ($table) => (int)($table['id'] ?? 0), $response['json']['larger_tables'] ?? []);
        $busy = array_map('intval', $response['json']['busy_table_ids'] ?? []);

        $this->assertNotContains($smallId, $available);
        $this->assertNotContains($busyId, $available);
        $this->assertContains($freeId, $available);
        $this->assertContains($freeId, $recommended);
        $this->assertContains($largerId, $larger);
        $this->assertNotContains($largerId, $recommended);
        $this->assertContains($busyId, $busy);
        $this->assertSame(4, (int)($response['json']['query']['oversized_warning_seat_gap'] ?? 0));
    }

    public function testBookingAuditTrailCapturesLifecycleEvents(): void
    {
        $tableA = $this->createTable('Audit A', 4);
        $tableB = $this->createTable('Audit B', 4);

        $createToken = $this->staff->csrfToken();
        $create = $this->staff->post('/api/bookings/create', [
            'guest_name' => 'Audit Guest',
            'party_size' => 4,
            'booking_start' => '2026-03-29 18:00:00',
            'booking_end' => '2026-03-29 19:00:00',
        ], ['X-CSRF-Token' => $createToken]);
        $this->assertSame(201, $create['status']);

        $bookingId = (int)($create['json']['booking']['id'] ?? 0);
        $this->assertGreaterThan(0, $bookingId);

        $assignToken = $this->staff->csrfToken();
        $assign = $this->staff->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $tableA,
        ], ['X-CSRF-Token' => $assignToken]);
        $this->assertSame(200, $assign['status']);

        $reassignToken = $this->staff->csrfToken();
        $reassign = $this->staff->post('/api/bookings/assign_table/' . $bookingId, [
            'table_id' => $tableB,
        ], ['X-CSRF-Token' => $reassignToken]);
        $this->assertSame(200, $reassign['status']);

        $updateToken = $this->staff->csrfToken();
        $update = $this->staff->post('/api/bookings/update/' . $bookingId, [
            'status' => 'confirmed',
            'notes' => 'Anniversary',
        ], ['X-CSRF-Token' => $updateToken]);
        $this->assertSame(200, $update['status']);

        $cancelToken = $this->staff->csrfToken();
        $cancel = $this->staff->post('/api/bookings/cancel/' . $bookingId, [], ['X-CSRF-Token' => $cancelToken]);
        $this->assertSame(200, $cancel['status']);

        $show = $this->staff->get('/api/bookings/show/' . $bookingId);
        $this->assertSame(200, $show['status']);
        $events = $show['json']['events'] ?? [];
        $this->assertIsArray($events);
        $this->assertGreaterThan(0, count($events));

        $eventTypes = array_map(fn ($event) => (string)($event['event_type'] ?? ''), $events);

        $this->assertContains('booking_created', $eventTypes);
        $this->assertContains('booking_table_assigned', $eventTypes);
        $this->assertContains('booking_table_reassigned', $eventTypes);
        $this->assertContains('booking_status_changed', $eventTypes);
        $this->assertContains('booking_details_updated', $eventTypes);
        $this->assertContains('booking_cancelled', $eventTypes);
    }

    private function signupClient(HttpClient $client, string $email): void
    {
        $token = $client->csrfToken();
        $response = $client->post('/api/auth/signup', [
            'name' => 'Booking Staff',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $token]);

        if ($response['status'] === 201)
        {
            return;
        }

        $this->assertSame(422, $response['status']);

        $loginToken = $client->csrfToken();
        $login = $client->post('/api/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ], ['X-CSRF-Token' => $loginToken]);

        $this->assertSame(200, $login['status']);
    }

    private function createTable(string $name, int $seats): int
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/tables/create', [
            'name' => $name,
            'seats' => $seats,
            'is_active' => true,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);

        return (int)($response['json']['table']['id'] ?? 0);
    }

    private function createBooking(string $guestName, int $partySize, string $start, string $end): int
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/bookings/create', [
            'guest_name' => $guestName,
            'party_size' => $partySize,
            'booking_start' => $start,
            'booking_end' => $end,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);

        return (int)($response['json']['booking']['id'] ?? 0);
    }

    private function createCustomerClient(string $prefix): HttpClient
    {
        $customer = new HttpClient(self::baseUrl());
        $email = $prefix . str_replace('.', '', uniqid('', true)) . '@example.com';

        $signupToken = $customer->csrfToken();
        $signup = $customer->post('/api/auth/signup', [
            'name' => 'Booking Customer',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $signupToken]);

        $this->assertSame(201, $signup['status']);
        $this->assertSame('customer', $signup['json']['user']['role'] ?? null);

        return $customer;
    }
}
