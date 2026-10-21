import React, { useState } from 'react';
import { SelectedVehicleSpec } from './VehicleSelector';

export interface NearbyShopItem {
    id: string;
    name: string;
    slug: string;
    street: string;
    city: string;
    state: string;
    zip_code: string;
    phone: string;
    base_labor_rate: string | number;
    is_certified: boolean;
    distance_miles: number;
    services?: Array<{
        id: string;
        name: string;
        category: string;
        pricing_type?: 'hourly' | 'fixed' | 'quote_only';
        fixed_price?: string | number | null;
        hourly_rate?: string | number | null;
    }>;
}

export interface ServiceOption {
    id: string;
    name: string;
    category: string;
}

interface ShopLocatorProps {
    services: ServiceOption[];
    selectedVehicle: SelectedVehicleSpec | null;
    initialShops?: NearbyShopItem[];
    initialZip?: string;
    onSearch: (params: {
        zipCode: string;
        latitude: number;
        longitude: number;
        radiusMiles: number;
        serviceId?: string;
    }) => Promise<{
        shops: NearbyShopItem[];
        resolvedLocation?: { city: string; state: string; latitude: number; longitude: number };
        error?: string;
    }>;
    onRequestQuote?: (shop: NearbyShopItem, serviceId?: string) => void;
}

/**
 * Built-in US Metro ZIP coordinate table for immediate client-side lat/lng conversion
 * prior to querying `GeoSearchService::findNearbyShops()`.
 */
const US_ZIP_COORDINATES: Record<
    string,
    { city: string; state: string; latitude: number; longitude: number }
> = {
    '94102': { city: 'San Francisco', state: 'CA', latitude: 37.7793, longitude: -122.4193 },
    '94103': { city: 'San Francisco', state: 'CA', latitude: 37.7726, longitude: -122.411 },
    '94107': { city: 'San Francisco', state: 'CA', latitude: 37.7621, longitude: -122.3971 },
    '90001': { city: 'Los Angeles', state: 'CA', latitude: 33.9731, longitude: -118.2479 },
    '90210': { city: 'Beverly Hills', state: 'CA', latitude: 34.0901, longitude: -118.4065 },
    '78701': { city: 'Austin', state: 'TX', latitude: 30.2711, longitude: -97.7437 },
    '75201': { city: 'Dallas', state: 'TX', latitude: 32.7876, longitude: -96.7994 },
    '60601': { city: 'Chicago', state: 'IL', latitude: 41.8853, longitude: -87.6219 },
    '10001': { city: 'New York', state: 'NY', latitude: 40.7506, longitude: -73.9972 },
    '30303': { city: 'Atlanta', state: 'GA', latitude: 33.7537, longitude: -84.3902 },
    '33101': { city: 'Miami', state: 'FL', latitude: 25.7751, longitude: -80.1947 },
    '98101': { city: 'Seattle', state: 'WA', latitude: 47.6101, longitude: -122.3344 },
};

export default function ShopLocator({
    services,
    selectedVehicle,
    initialShops = [],
    initialZip = '94102',
    onSearch,
    onRequestQuote,
}: ShopLocatorProps) {
    const [zipCode, setZipCode] = useState<string>(initialZip);
    const [radiusMiles, setRadiusMiles] = useState<number>(25);
    const [selectedServiceId, setSelectedServiceId] = useState<string>('');
    const [shops, setShops] = useState<NearbyShopItem[]>(initialShops);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [searchMeta, setSearchMeta] = useState<{
        city: string;
        state: string;
        latitude: number;
        longitude: number;
    } | null>(US_ZIP_COORDINATES[initialZip] ?? null);

    const handleSearchSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        const cleanZip = zipCode.trim().slice(0, 5);
        if (!/^[0-9]{5}$/.test(cleanZip)) {
            setError('Please enter a valid 5-digit US ZIP code.');
            return;
        }

        const localCentroid = US_ZIP_COORDINATES[cleanZip] ?? {
            city: 'US Metro Area',
            state: 'US',
            latitude: 37.7749,
            longitude: -122.4194,
        };

        setLoading(true);
        try {
            const response = await onSearch({
                zipCode: cleanZip,
                latitude: localCentroid.latitude,
                longitude: localCentroid.longitude,
                radiusMiles,
                serviceId: selectedServiceId || undefined,
            });

            if (response.error) {
                setError(response.error);
                setShops([]);
                return;
            }

            setShops(response.shops);
            setSearchMeta(response.resolvedLocation ?? localCentroid);
        } catch {
            setError('Unable to search nearby certified shops right now. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="mb-4">
                    <span className="text-xs font-bold uppercase tracking-wider text-blue-600">
                        Step 2 · Certified Shop Search
                    </span>
                    <h2 className="text-lg font-bold text-slate-900">
                        Find Certified Repair Shops Near Your ZIP Code
                    </h2>
                </div>

                <form
                    onSubmit={handleSearchSubmit}
                    className="grid grid-cols-1 gap-4 md:grid-cols-12"
                >
                    <div className="md:col-span-3">
                        <label
                            htmlFor="zip-input"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            US ZIP Code
                        </label>
                        <input
                            id="zip-input"
                            type="text"
                            inputMode="numeric"
                            maxLength={5}
                            value={zipCode}
                            onChange={(e) =>
                                setZipCode(e.target.value.replace(/[^0-9]/g, ''))
                            }
                            placeholder="e.g., 94102"
                            className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        />
                    </div>

                    <div className="md:col-span-3">
                        <label
                            htmlFor="radius-select"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Search Radius
                        </label>
                        <select
                            id="radius-select"
                            value={radiusMiles}
                            onChange={(e) => setRadiusMiles(Number(e.target.value))}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option value={10}>Within 10 miles</option>
                            <option value={25}>Within 25 miles</option>
                            <option value={50}>Within 50 miles</option>
                        </select>
                    </div>

                    <div className="md:col-span-4">
                        <label
                            htmlFor="service-select"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Repair Service (Optional)
                        </label>
                        <select
                            id="service-select"
                            value={selectedServiceId}
                            onChange={(e) => setSelectedServiceId(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option value="">All Certified Repair Services</option>
                            {services.map((srv) => (
                                <option key={srv.id} value={srv.id}>
                                    {srv.category} — {srv.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-end md:col-span-2">
                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            {loading ? 'Searching...' : 'Find Shops'}
                        </button>
                    </div>
                </form>

                {error && (
                    <div
                        role="alert"
                        className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium text-red-700"
                    >
                        {error}
                    </div>
                )}

                {searchMeta && !error && (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span>
                            Showing verified certified shops within{' '}
                            <strong className="text-slate-700">{radiusMiles} miles</strong> of{' '}
                            <strong className="text-slate-700">
                                {searchMeta.city}, {searchMeta.state} ({zipCode})
                            </strong>{' '}
                            ({searchMeta.latitude.toFixed(4)}, {searchMeta.longitude.toFixed(4)})
                        </span>
                        <span>{shops.length} certified shops found</span>
                    </div>
                )}
            </div>

            <div className="space-y-4">
                {shops.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                        <h3 className="text-base font-bold text-slate-900">
                            No Certified Shops Found in This Radius
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Try expanding your search radius to 50 miles or testing a pilot ZIP code
                            such as 94102, 78701, or 90001.
                        </p>
                    </div>
                ) : (
                    shops.map((shop) => (
                        <article
                            key={shop.id}
                            className="flex flex-col justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-blue-300 md:flex-row md:items-center"
                        >
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2.5">
                                    <h3 className="text-lg font-bold text-slate-900">
                                        {shop.name}
                                    </h3>
                                    {shop.is_certified && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                            ✓ RepairPal Certified
                                        </span>
                                    )}
                                    <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                        {Number(shop.distance_miles).toFixed(1)} mi away
                                    </span>
                                </div>

                                <p className="text-sm text-slate-600">
                                    {shop.street}, {shop.city}, {shop.state} {shop.zip_code} ·{' '}
                                    <span className="font-medium text-slate-800">{shop.phone}</span>
                                </p>

                                {selectedVehicle && (
                                    <p className="text-xs font-medium text-slate-500">
                                        Servicing your{' '}
                                        <span className="font-semibold text-slate-700">
                                            {selectedVehicle.year} {selectedVehicle.make}{' '}
                                            {selectedVehicle.model}
                                        </span>
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col items-start gap-3 border-t border-slate-100 pt-4 md:items-end md:border-t-0 md:pt-0">
                                <div className="text-left md:text-right">
                                    <span className="block text-xs text-slate-500">
                                        Verified Shop Labor Rate
                                    </span>
                                    <strong className="text-xl font-extrabold text-slate-900">
                                        ${Number(shop.base_labor_rate).toFixed(2)}
                                        <span className="text-xs font-normal text-slate-500">
                                            /hr
                                        </span>
                                    </strong>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onRequestQuote?.(shop, selectedServiceId || undefined)
                                        }
                                        className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-blue-700"
                                    >
                                        Request Estimate / Book
                                    </button>
                                </div>
                            </div>
                        </article>
                    ))
                )}
            </div>
        </div>
    );
}
