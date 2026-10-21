import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import VehicleSelector, {
    SelectedVehicleSpec,
    VehicleTaxonomyOption,
} from '../Components/VehicleSelector';
import ShopLocator, { NearbyShopItem, ServiceOption } from '../Components/ShopLocator';

interface HomePageProps {
    vehicleCatalog: VehicleTaxonomyOption[];
    services: ServiceOption[];
    initialShops: NearbyShopItem[];
    initialZip?: string;
}

export default function Home({
    vehicleCatalog = [],
    services = [],
    initialShops = [],
    initialZip = '94102',
}: HomePageProps) {
    const [selectedVehicle, setSelectedVehicle] = useState<SelectedVehicleSpec | null>(
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
            attributes: payload.attributes ?? null,
            error: payload.error ?? null,
        };
    };

    const handleShopSearch = async (params: {
        zipCode: string;
        latitude: number;
        longitude: number;
        radiusMiles: number;
        serviceId?: string;
    }) => {
        const query = new URLSearchParams({
            zip_code: params.zipCode,
            lat: String(params.latitude),
            lng: String(params.longitude),
            radius_miles: String(params.radiusMiles),
        });

        if (params.serviceId) {
            query.set('service_id', params.serviceId);
        }

        const response = await fetch(`/api/v1/shops?${query.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        const payload = await response.json();
        return {
            shops: payload.data ?? [],
            resolvedLocation: payload.meta?.location ?? undefined,
            error: payload.error ?? undefined,
        };
    };

    return (
        <>
            <Head title="Find Certified Auto Repair Shops & Transparent Estimates" />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                {/* Top Navigation */}
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 font-black text-white">
                                RP
                            </span>
                            <div>
                                <span className="text-base font-extrabold tracking-tight text-slate-900">
                                    RepairPal Marketplace
                                </span>
                                <span className="block text-[11px] font-medium text-slate-500">
                                    Certified US Auto Repair Network
                                </span>
                            </div>
                        </div>

                        <nav className="flex items-center gap-4 text-xs font-semibold text-slate-600">
                            <a href="/provider" className="hover:text-blue-600">
                                For Repair Shops
                            </a>
                            <a
                                href="/account"
                                className="rounded-xl border border-slate-200 px-3.5 py-2 text-slate-800 hover:border-blue-400"
                            >
                                Customer Account
                            </a>
                        </nav>
                    </div>
                </header>

                {/* Hero Banner */}
                <section className="bg-slate-900 py-12 text-white">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="max-w-3xl">
                            <span className="inline-flex items-center rounded-full bg-blue-500/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-blue-300">
                                Verified Shops · Transparent Labor Rates
                            </span>
                            <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                                Fair, Evidence-Backed Auto Repair With Certified Local Shops.
                            </h1>
                            <p className="mt-3 text-sm leading-relaxed text-slate-300">
                                Select your vehicle or decode your 17-digit VIN, compare certified
                                nearby repair shops by PostGIS radius distance, and request a
                                guaranteed quote or appointment.
                            </p>
                        </div>
                    </div>
                </section>

                {/* Main Interactive Directory Workspace */}
                <main className="mx-auto -mt-6 max-w-7xl space-y-6 px-4 pb-16 sm:px-6 lg:px-8">
                    <VehicleSelector
                        catalog={vehicleCatalog}
                        value={selectedVehicle}
                        onChange={setSelectedVehicle}
                        onDecodeVin={handleDecodeVin}
                    />

                    <ShopLocator
                        services={services}
                        selectedVehicle={selectedVehicle}
                        initialShops={initialShops}
                        initialZip={initialZip}
                        onSearch={handleShopSearch}
                    />
                </main>
            </div>
        </>
    );
}
