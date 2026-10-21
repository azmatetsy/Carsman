import React, { useState } from 'react';
import { NearbyShopItem, ServiceOption } from './ShopLocator';
import { SelectedVehicleSpec } from './VehicleSelector';

interface EstimateAndBookingModalProps {
    shop: NearbyShopItem;
    services: ServiceOption[];
    selectedVehicle: SelectedVehicleSpec | null;
    defaultServiceId?: string;
    defaultZipCode: string;
    onClose: () => void;
}

interface EstimateResultPayload {
    id: string;
    is_supported_estimate: boolean;
    source_version: string;
    participating_shops_count: number;
    estimated_labor_hours: string | number | null;
    estimated_labor_low: string | number | null;
    estimated_labor_high: string | number | null;
    estimated_parts_low: string | number | null;
    estimated_parts_high: string | number | null;
    estimated_fees: string | number | null;
    estimated_total_low: string | number | null;
    estimated_total_high: string | number | null;
    expires_at: string;
    assumptions_payload?: {
        policy?: string;
        disclosure?: string;
        reason?: string;
    };
}

export default function EstimateAndBookingModal({
    shop,
    services,
    selectedVehicle,
    defaultServiceId = '',
    defaultZipCode,
    onClose,
}: EstimateAndBookingModalProps) {
    const [serviceId, setServiceId] = useState<string>(
        defaultServiceId || (services[0]?.id ?? ''),
    );
    const [issueDescription, setIssueDescription] = useState<string>('');
    const [estimate, setEstimate] = useState<EstimateResultPayload | null>(null);
    const [calculating, setCalculating] = useState<boolean>(false);

    const [customerName, setCustomerName] = useState<string>('');
    const [customerEmail, setCustomerEmail] = useState<string>('');
    const [customerPhone, setCustomerPhone] = useState<string>('');
    const [preferredDate, setPreferredDate] = useState<string>(() => {
        const tomorrow = new Date(Date.now() + 86400000);
        return tomorrow.toISOString().slice(0, 10);
    });
    const [preferredTimeSlot, setPreferredTimeSlot] = useState<
        'morning' | 'afternoon' | 'dropoff'
    >('morning');

    const [submitting, setSubmitting] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [confirmationMessage, setConfirmationMessage] = useState<string | null>(null);

    const handleCalculateEstimate = async () => {
        setError(null);

        if (!selectedVehicle?.id) {
            setError(
                'Please select your Year, Make, Model, and Trim in Step 1 before calculating an estimate.',
            );
            return;
        }

        if (!serviceId) {
            setError('Please select a repair service.');
            return;
        }

        setCalculating(true);
        try {
            const response = await fetch('/api/v1/estimates', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    vehicle_id: selectedVehicle.id,
                    service_id: serviceId,
                    zip_code: defaultZipCode || shop.zip_code,
                    issue_description: issueDescription || null,
                }),
            });

            const payload = await response.json();
            if (!response.ok || !payload.data) {
                setError(payload.message ?? payload.error ?? 'Could not calculate estimate.');
                return;
            }

            setEstimate(payload.data);
        } catch {
            setError('Network error while calculating estimate.');
        } finally {
            setCalculating(false);
        }
    };

    const handleBookOrRequestQuote = async (mode: 'booking' | 'quote') => {
        setError(null);

        if (!selectedVehicle?.id) {
            setError('Please select your vehicle in Step 1 first.');
            return;
        }

        if (!customerName.trim() || !customerEmail.trim()) {
            setError('Please enter your name and email address.');
            return;
        }

        if (mode === 'booking' && !customerPhone.trim()) {
            setError('Please provide a phone number for appointment confirmation.');
            return;
        }

        setSubmitting(true);
        try {
            if (mode === 'quote') {
                const response = await fetch('/api/v1/quotes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        shop_id: shop.id,
                        vehicle_id: selectedVehicle.id,
                        service_id: serviceId,
                        estimate_request_id: estimate?.id ?? null,
                        customer_name: customerName,
                        customer_email: customerEmail,
                        customer_phone: customerPhone || null,
                        customer_issue_notes: issueDescription || null,
                    }),
                });

                const payload = await response.json();
                if (!response.ok) {
                    setError(payload.message ?? payload.error ?? 'Failed to submit quote request.');
                    return;
                }

                setConfirmationMessage(
                    `Quote request submitted to ${shop.name}. Track shop responses in your Customer Account.`,
                );
                return;
            }

            const idempotencyKey = `${shop.id}-${selectedVehicle.id}-${serviceId}-${preferredDate}-${preferredTimeSlot}`
                .slice(0, 64);

            const response = await fetch('/api/v1/bookings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'Idempotency-Key': idempotencyKey,
                },
                body: JSON.stringify({
                    shop_id: shop.id,
                    vehicle_id: selectedVehicle.id,
                    service_id: serviceId,
                    preferred_date: preferredDate,
                    preferred_time_slot: preferredTimeSlot,
                    customer_name: customerName,
                    customer_email: customerEmail,
                    customer_phone: customerPhone,
                    issue_description: issueDescription || null,
                }),
            });

            const payload = await response.json();
            if (!response.ok) {
                setError(payload.message ?? payload.error ?? 'Failed to submit appointment request.');
                return;
            }

            setConfirmationMessage(
                `Appointment requested with ${shop.name} for ${preferredDate} (${preferredTimeSlot}). View status in your Customer Account.`,
            );
        } catch {
            setError('Network error while submitting request.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                <div className="flex items-start justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span className="text-xs font-bold uppercase tracking-wider text-blue-600">
                            Estimate & Appointment Request
                        </span>
                        <h2 className="text-xl font-extrabold text-slate-900">
                            {shop.name}
                        </h2>
                        <p className="text-xs text-slate-500">
                            {shop.street}, {shop.city}, {shop.state} {shop.zip_code} · Base Rate: $
                            {Number(shop.base_labor_rate).toFixed(2)}/hr
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        ✕
                    </button>
                </div>

                {confirmationMessage ? (
                    <div className="my-6 space-y-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
                        <div className="text-base font-bold text-emerald-900">
                            ✓ Request Received
                        </div>
                        <p className="text-sm text-emerald-800">{confirmationMessage}</p>
                        <div className="flex justify-center gap-3 pt-2">
                            <a
                                href={`/account?email=${encodeURIComponent(customerEmail)}`}
                                className="rounded-xl bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800"
                            >
                                Open Customer Account
                            </a>
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="mt-5 space-y-5">
                        {/* Vehicle & Service Selection */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Selected Vehicle
                                </label>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-medium text-slate-800">
                                    {selectedVehicle
                                        ? `${selectedVehicle.year} ${selectedVehicle.make} ${selectedVehicle.model} ${selectedVehicle.submodel ?? ''}`
                                        : 'Select a vehicle in Step 1 on the directory'}
                                </div>
                            </div>

                            <div>
                                <label
                                    htmlFor="modal-service"
                                    className="mb-1 block text-xs font-semibold text-slate-700"
                                >
                                    Repair Service
                                </label>
                                <select
                                    id="modal-service"
                                    value={serviceId}
                                    onChange={(e) => {
                                        setServiceId(e.target.value);
                                        setEstimate(null);
                                    }}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900"
                                >
                                    {services.map((srv) => (
                                        <option key={srv.id} value={srv.id}>
                                            {srv.category} — {srv.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Preliminary Estimate Box (AUTO-05) */}
                        <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900">
                                        Preliminary Fair-Price Estimate
                                    </h3>
                                    <p className="text-xs text-slate-600">
                                        Computed only when verified participating-shop pricing data
                                        exists in your ZIP area.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    disabled={calculating || !selectedVehicle?.id}
                                    onClick={handleCalculateEstimate}
                                    className="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {calculating ? 'Calculating...' : 'Calculate Estimate Range'}
                                </button>
                            </div>

                            {estimate && (
                                <div className="mt-4 border-t border-blue-200/70 pt-3">
                                    {estimate.is_supported_estimate ? (
                                        <div className="space-y-2">
                                            <div className="flex items-baseline justify-between">
                                                <span className="text-xs font-bold uppercase text-blue-800">
                                                    Estimated Total Range (Before Tax)
                                                </span>
                                                <strong className="text-xl font-extrabold text-slate-900">
                                                    ${Number(estimate.estimated_total_low).toFixed(2)} – $
                                                    {Number(estimate.estimated_total_high).toFixed(2)}
                                                </strong>
                                            </div>
                                            <div className="grid grid-cols-3 gap-2 text-xs text-slate-600">
                                                <div>
                                                    Labor: $
                                                    {Number(estimate.estimated_labor_low).toFixed(0)}–$
                                                    {Number(estimate.estimated_labor_high).toFixed(0)}
                                                </div>
                                                <div>
                                                    Parts: $
                                                    {Number(estimate.estimated_parts_low).toFixed(0)}–$
                                                    {Number(estimate.estimated_parts_high).toFixed(0)}
                                                </div>
                                                <div>
                                                    Supplies/Fees: $
                                                    {Number(estimate.estimated_fees).toFixed(2)}
                                                </div>
                                            </div>
                                            <p className="text-[11px] text-slate-500">
                                                Source: {estimate.source_version} ·{' '}
                                                {estimate.assumptions_payload?.disclosure}
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                                            <strong>Custom Shop Quote Recommended: </strong>
                                            {estimate.assumptions_payload?.reason} Submit a direct quote
                                            request below with zero obligation.
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Customer & Booking Inputs (AUTO-06) */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Your Name
                                </label>
                                <input
                                    type="text"
                                    value={customerName}
                                    onChange={(e) => setCustomerName(e.target.value)}
                                    placeholder="Elena Rostova"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Email Address
                                </label>
                                <input
                                    type="email"
                                    value={customerEmail}
                                    onChange={(e) => setCustomerEmail(e.target.value)}
                                    placeholder="customer@repairpal.test"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Phone Number
                                </label>
                                <input
                                    type="tel"
                                    value={customerPhone}
                                    onChange={(e) => setCustomerPhone(e.target.value)}
                                    placeholder="(415) 555-0199"
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Preferred Appointment Date
                                </label>
                                <input
                                    type="date"
                                    value={preferredDate}
                                    onChange={(e) => setPreferredDate(e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Preferred Drop-Off Window
                                </label>
                                <select
                                    value={preferredTimeSlot}
                                    onChange={(e) =>
                                        setPreferredTimeSlot(
                                            e.target.value as 'morning' | 'afternoon' | 'dropoff',
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="morning">Morning (8:00 AM – 12:00 PM)</option>
                                    <option value="afternoon">Afternoon (12:00 PM – 5:00 PM)</option>
                                    <option value="dropoff">Early Bird / Key Drop-Off</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700">
                                Symptoms or Repair Notes (Optional)
                            </label>
                            <textarea
                                rows={2}
                                value={issueDescription}
                                onChange={(e) => setIssueDescription(e.target.value)}
                                placeholder="Describe any squealing noise, warning lights, or inspection requests..."
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>

                        {error && (
                            <div
                                role="alert"
                                className="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-medium text-red-700"
                            >
                                {error}
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                disabled={submitting}
                                onClick={() => handleBookOrRequestQuote('quote')}
                                className="rounded-xl border border-blue-600 bg-white px-4 py-2.5 text-xs font-bold text-blue-600 hover:bg-blue-50 disabled:opacity-50"
                            >
                                Request Written Shop Quote
                            </button>
                            <button
                                type="button"
                                disabled={submitting}
                                onClick={() => handleBookOrRequestQuote('booking')}
                                className="rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {submitting ? 'Submitting...' : 'Request Appointment'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
