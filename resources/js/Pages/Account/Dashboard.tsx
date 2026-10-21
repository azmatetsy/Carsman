import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';

interface CustomerDashboardProps {
    customerEmail: string;
    bookings: Array<{
        id: string;
        status: string;
        preferred_date: string;
        preferred_time_slot: string;
        scheduled_at: string | null;
        alternate_proposed_at: string | null;
        customer_email: string;
        shop_notes: string | null;
        shop?: { name: string; slug: string; phone: string };
        vehicle?: { year: number; make: string; model: string };
        service?: { name: string; category: string };
    }>;
    quotes: Array<{
        id: string;
        status: string;
        total_amount: string | number;
        valid_until: string | null;
        revision_number: number;
        scope_notes: string | null;
        shop?: { name: string; slug: string };
        vehicle?: { year: number; make: string; model: string };
        service?: { name: string };
    }>;
}

export default function Dashboard({
    customerEmail,
    bookings: initialBookings = [],
    quotes = [],
}: CustomerDashboardProps) {
    const [bookings, setBookings] = useState(initialBookings);
    const [cancellingId, setCancellingId] = useState<string | null>(null);

    const handleCancelBooking = async (bookingId: string) => {
        setCancellingId(bookingId);
        try {
            const response = await fetch(`/api/v1/bookings/${bookingId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    reason: 'Cancelled by customer from Customer Portal.',
                    customer_email: customerEmail,
                }),
            });

            const payload = await response.json();
            if (response.ok && payload.data) {
                setBookings((prev) =>
                    prev.map((b) =>
                        b.id === bookingId ? { ...b, status: payload.data.status } : b,
                    ),
                );
            }
        } finally {
            setCancellingId(null);
        }
    };

    return (
        <>
            <Head title="Customer Account · Appointments & Shop Quotes" />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                        <Link
                            href="/"
                            className="text-sm font-bold text-blue-600 hover:text-blue-700"
                        >
                            ← Back to RepairPal Marketplace
                        </Link>
                        <span className="text-xs font-semibold text-slate-500">
                            Account: {customerEmail}
                        </span>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6">
                    <div>
                        <h1 className="text-2xl font-extrabold text-slate-900">
                            My Appointments & Repair Quotes
                        </h1>
                        <p className="text-sm text-slate-600">
                            Track your appointment requests, confirmed service times, and itemized
                            shop quotes.
                        </p>
                    </div>

                    {/* Bookings Section */}
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-slate-900">
                            Service Appointments ({bookings.length})
                        </h2>

                        {bookings.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">
                                No appointment requests yet. Search for a certified shop on the home
                                page to schedule service.
                            </p>
                        ) : (
                            <div className="mt-4 divide-y divide-slate-100">
                                {bookings.map((booking) => {
                                    const canCancel = [
                                        'requested',
                                        'confirmed',
                                        'rescheduled',
                                    ].includes(booking.status);

                                    return (
                                        <div
                                            key={booking.id}
                                            className="flex flex-col justify-between gap-4 py-4 md:flex-row md:items-center"
                                        >
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold text-slate-900">
                                                        {booking.shop?.name ?? 'Certified Shop'}
                                                    </span>
                                                    <span className="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold uppercase text-blue-700">
                                                        {booking.status}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-slate-600">
                                                    {booking.vehicle
                                                        ? `${booking.vehicle.year} ${booking.vehicle.make} ${booking.vehicle.model} · `
                                                        : ''}
                                                    <strong>{booking.service?.name}</strong>
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    Requested Date:{' '}
                                                    {booking.preferred_date.slice(0, 10)} (
                                                    {booking.preferred_time_slot})
                                                    {booking.scheduled_at
                                                        ? ` · Confirmed: ${booking.scheduled_at.slice(0, 16)}`
                                                        : ''}
                                                </p>
                                            </div>

                                            {canCancel && (
                                                <button
                                                    type="button"
                                                    disabled={cancellingId === booking.id}
                                                    onClick={() => handleCancelBooking(booking.id)}
                                                    className="rounded-xl border border-red-200 px-3.5 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50 disabled:opacity-50"
                                                >
                                                    {cancellingId === booking.id
                                                        ? 'Cancelling...'
                                                        : 'Cancel Appointment'}
                                                </button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </section>

                    {/* Quotes Section */}
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-slate-900">
                            Shop Repair Quotes ({quotes.length})
                        </h2>

                        {quotes.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">
                                No direct shop quotes requested yet.
                            </p>
                        ) : (
                            <div className="mt-4 divide-y divide-slate-100">
                                {quotes.map((quote) => (
                                    <div
                                        key={quote.id}
                                        className="flex flex-col justify-between gap-4 py-4 md:flex-row md:items-center"
                                    >
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-slate-900">
                                                    {quote.shop?.name}
                                                </span>
                                                <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold uppercase text-slate-700">
                                                    {quote.status} · Rev #{quote.revision_number}
                                                </span>
                                            </div>
                                            <p className="text-sm text-slate-600">
                                                {quote.vehicle
                                                    ? `${quote.vehicle.year} ${quote.vehicle.make} ${quote.vehicle.model} · `
                                                    : ''}
                                                {quote.service?.name}
                                            </p>
                                            {quote.scope_notes && (
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {quote.scope_notes}
                                                </p>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <span className="block text-xs text-slate-500">
                                                Quoted Total
                                            </span>
                                            <strong className="text-lg font-extrabold text-slate-900">
                                                {Number(quote.total_amount) > 0
                                                    ? `$${Number(quote.total_amount).toFixed(2)}`
                                                    : 'Awaiting Shop Response'}
                                            </strong>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </main>
            </div>
        </>
    );
}
