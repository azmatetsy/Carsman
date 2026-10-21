import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import VehicleSelector, {
    SelectedVehicleSpec,
    VehicleTaxonomyOption,
} from '../Components/VehicleSelector';
import EstimateAndBookingModal from '../Components/EstimateAndBookingModal';

interface ShopDetailProps {
    shop: {
        id: string;
        name: string;
        slug: string;
        email: string;
        phone: string;
        street: string;
        city: string;
        state: string;
        zip_code: string;
        base_labor_rate: string | number;
        is_certified: boolean;
        services?: Array<{
            id: string;
            name: string;
            category: string;
            pivot?: {
                pricing_type: 'hourly' | 'fixed' | 'quote_only';
                hourly_rate: string | number | null;
                fixed_price: string | number | null;
                estimated_minutes: number | null;
            };
        }>;
        availability_slots?: Array<{
            id: string;
            day_of_week: number;
            opens_at: string;
            closes_at: string;
            is_closed: boolean;
        }>;
    };
    vehicleCatalog?: VehicleTaxonomyOption[];
}

const DAY_NAMES = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

export default function ShopDetail({ shop, vehicleCatalog = [] }: ShopDetailProps) {
    const [selectedVehicle, setSelectedVehicle] = useState<SelectedVehicleSpec | null>(
        null,
    );
    const [modalOpenForServiceId, setModalOpenForServiceId] = useState<string | null>(
        null,
    );

    const handleDecodeVin = async (vin: string) => {
        const response = await fetch('/api/v1/vehicles/decode-vin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({ vin }),
        });

        const payload = await response.json();
        return {
            success: Boolean(payload.success),
            attributes: payload.attributes
                ? {
                      ...payload.attributes,
                      id: payload.vehicle?.id ?? undefined,
                  }
                : null,
            error: payload.error ?? null,
        };
    };

    const shopServicesList = (shop.services ?? []).map((s) => ({
        id: s.id,
        name: s.name,
        category: s.category,
    }));

    return (
        <>
            <Head title={`${shop.name} · Certified Auto Repair in ${shop.city}, ${shop.state}`} />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                        <Link
                            href="/"
                            className="text-sm font-bold text-blue-600 hover:text-blue-700"
                        >
                            ← Back to Shop Directory
                        </Link>
                        <span className="text-xs font-semibold text-slate-500">
                            RepairPal Verified Partner Directory
                        </span>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                            <div>
                                <div className="flex flex-wrap items-center gap-2.5">
                                    <h1 className="text-2xl font-extrabold text-slate-900">
                                        {shop.name}
                                    </h1>
                                    {shop.is_certified && (
                                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                                            ✓ RepairPal Certified
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-slate-600">
                                    {shop.street}, {shop.city}, {shop.state} {shop.zip_code}
                                </p>
                                <p className="mt-1 text-sm font-semibold text-slate-800">
                                    {shop.phone} · {shop.email}
                                </p>
                            </div>

                            <div className="flex flex-col items-start gap-3 md:items-end">
                                <div className="rounded-xl bg-slate-50 p-3.5 text-left md:text-right">
                                    <span className="block text-xs text-slate-500">
                                        Standard Shop Labor Rate
                                    </span>
                                    <strong className="text-2xl font-extrabold text-slate-900">
                                        ${Number(shop.base_labor_rate).toFixed(2)}/hr
                                    </strong>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setModalOpenForServiceId(
                                            shopServicesList[0]?.id ?? '',
                                        )
                                    }
                                    className="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700"
                                >
                                    Request Estimate / Book Appointment
                                </button>
                            </div>
                        </div>
                    </section>

                    <VehicleSelector
                        catalog={vehicleCatalog}
                        value={selectedVehicle}
                        onChange={setSelectedVehicle}
                        onDecodeVin={handleDecodeVin}
                    />

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                            <h2 className="text-lg font-bold text-slate-900">
                                Supported Repair Services & Indicative Pricing
                            </h2>
                            <div className="mt-4 divide-y divide-slate-100">
                                {(shop.services ?? []).map((service) => (
                                    <div
                                        key={service.id}
                                        className="flex items-center justify-between gap-4 py-3.5"
                                    >
                                        <div>
                                            <span className="text-xs font-semibold uppercase text-slate-400">
                                                {service.category}
                                            </span>
                                            <h3 className="text-sm font-bold text-slate-900">
                                                {service.name}
                                            </h3>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="text-right text-sm font-bold text-slate-900">
                                                {service.pivot?.pricing_type === 'fixed' &&
                                                service.pivot.fixed_price
                                                    ? `$${Number(service.pivot.fixed_price).toFixed(2)} Fixed`
                                                    : service.pivot?.pricing_type === 'hourly'
                                                      ? `$${Number(
                                                            service.pivot.hourly_rate ??
                                                                shop.base_labor_rate,
                                                        ).toFixed(2)}/hr`
                                                      : 'Custom Quote'}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setModalOpenForServiceId(service.id)
                                                }
                                                className="rounded-lg border border-blue-600 px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50"
                                            >
                                                Estimate / Book
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-slate-900">
                                Operating Hours
                            </h2>
                            <ul className="mt-4 space-y-2.5 text-sm">
                                {(shop.availability_slots ?? []).map((slot) => (
                                    <li
                                        key={slot.id}
                                        className="flex items-center justify-between text-slate-700"
                                    >
                                        <span className="font-medium">
                                            {DAY_NAMES[slot.day_of_week] ?? slot.day_of_week}
                                        </span>
                                        <span>
                                            {slot.is_closed
                                                ? 'Closed'
                                                : `${slot.opens_at.slice(0, 5)} – ${slot.closes_at.slice(0, 5)}`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>
                </main>

                {modalOpenForServiceId !== null && (
                    <EstimateAndBookingModal
                        shop={{
                            ...shop,
                            distance_miles: 0,
                        }}
                        services={shopServicesList}
                        selectedVehicle={selectedVehicle}
                        defaultServiceId={modalOpenForServiceId}
                        defaultZipCode={shop.zip_code}
                        onClose={() => setModalOpenForServiceId(null)}
                    />
                )}
            </div>
        </>
    );
}
