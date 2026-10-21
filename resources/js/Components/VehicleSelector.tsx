import React, { useMemo, useState } from 'react';

export interface VehicleTaxonomyOption {
    id: string;
    year: number;
    make: string;
    model: string;
    submodel: string;
    engine: string;
}

export interface SelectedVehicleSpec {
    id?: string;
    year: number;
    make: string;
    model: string;
    submodel?: string;
    engine?: string;
    vin?: string;
}

interface VehicleSelectorProps {
    catalog: VehicleTaxonomyOption[];
    value: SelectedVehicleSpec | null;
    onChange: (vehicle: SelectedVehicleSpec | null) => void;
    onDecodeVin?: (vin: string) => Promise<{
        success: boolean;
        attributes?: SelectedVehicleSpec | null;
        error?: string | null;
    }>;
}

export default function VehicleSelector({
    catalog,
    value,
    onChange,
    onDecodeVin,
}: VehicleSelectorProps) {
    const [mode, setMode] = useState<'ymm' | 'vin'>('ymm');
    const [selectedYear, setSelectedYear] = useState<number | ''>(value?.year ?? '');
    const [selectedMake, setSelectedMake] = useState<string>(value?.make ?? '');
    const [selectedModel, setSelectedModel] = useState<string>(value?.model ?? '');
    const [selectedTrimKey, setSelectedTrimKey] = useState<string>('');

    const [vinInput, setVinInput] = useState<string>(value?.vin ?? '');
    const [vinLoading, setVinLoading] = useState<boolean>(false);
    const [vinError, setVinError] = useState<string | null>(null);

    // 1. Distinct Years (descending)
    const years = useMemo<number[]>(() => {
        const set = new Set<number>(catalog.map((v) => v.year));
        return Array.from(set).sort((a, b) => b - a);
    }, [catalog]);

    // 2. Distinct Makes filtered by Year
    const makes = useMemo<string[]>(() => {
        if (!selectedYear) return [];
        const set = new Set<string>(
            catalog.filter((v) => v.year === Number(selectedYear)).map((v) => v.make),
        );
        return Array.from(set).sort((a, b) => a.localeCompare(b));
    }, [catalog, selectedYear]);

    // 3. Distinct Models filtered by Year + Make
    const models = useMemo<string[]>(() => {
        if (!selectedYear || !selectedMake) return [];
        const set = new Set<string>(
            catalog
                .filter((v) => v.year === Number(selectedYear) && v.make === selectedMake)
                .map((v) => v.model),
        );
        return Array.from(set).sort((a, b) => a.localeCompare(b));
    }, [catalog, selectedYear, selectedMake]);

    // 4. Trims / Engines filtered by Year + Make + Model
    const trimVariants = useMemo<VehicleTaxonomyOption[]>(() => {
        if (!selectedYear || !selectedMake || !selectedModel) return [];
        return catalog.filter(
            (v) =>
                v.year === Number(selectedYear) &&
                v.make === selectedMake &&
                v.model === selectedModel,
        );
    }, [catalog, selectedYear, selectedMake, selectedModel]);

    const handleYearChange = (yearStr: string) => {
        const nextYear = yearStr ? Number(yearStr) : '';
        setSelectedYear(nextYear);
        setSelectedMake('');
        setSelectedModel('');
        setSelectedTrimKey('');
        onChange(null);
    };

    const handleMakeChange = (nextMake: string) => {
        setSelectedMake(nextMake);
        setSelectedModel('');
        setSelectedTrimKey('');
        onChange(null);
    };

    const handleModelChange = (nextModel: string) => {
        setSelectedModel(nextModel);
        setSelectedTrimKey('');

        if (!selectedYear || !selectedMake || !nextModel) {
            onChange(null);
            return;
        }

        const matchingVariants = catalog.filter(
            (v) =>
                v.year === Number(selectedYear) &&
                v.make === selectedMake &&
                v.model === nextModel,
        );

        if (matchingVariants.length === 1) {
            const single = matchingVariants[0];
            setSelectedTrimKey(single.id);
            onChange({
                id: single.id,
                year: single.year,
                make: single.make,
                model: single.model,
                submodel: single.submodel,
                engine: single.engine,
            });
        } else {
            onChange({
                year: Number(selectedYear),
                make: selectedMake,
                model: nextModel,
            });
        }
    };

    const handleTrimChange = (variantId: string) => {
        setSelectedTrimKey(variantId);
        const variant = trimVariants.find((v) => v.id === variantId);

        if (variant) {
            onChange({
                id: variant.id,
                year: variant.year,
                make: variant.make,
                model: variant.model,
                submodel: variant.submodel,
                engine: variant.engine,
            });
        }
    };

    const handleVinSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setVinError(null);

        const normalizedVin = vinInput.trim().toUpperCase();
        if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(normalizedVin)) {
            setVinError(
                'Enter a valid 17-character VIN (letters I, O, and Q are not permitted).',
            );
            return;
        }

        if (!onDecodeVin) {
            return;
        }

        setVinLoading(true);
        try {
            const response = await onDecodeVin(normalizedVin);
            if (!response.success || !response.attributes) {
                setVinError(
                    response.error ??
                        'Could not decode VIN. Please select Year, Make, and Model manually.',
                );
                return;
            }

            const decoded = response.attributes;
            setSelectedYear(decoded.year);
            setSelectedMake(decoded.make);
            setSelectedModel(decoded.model);
            onChange({
                ...decoded,
                vin: normalizedVin,
            });
        } catch {
            setVinError('Network error while contacting NHTSA VIN service.');
        } finally {
            setVinLoading(false);
        }
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span className="text-xs font-bold uppercase tracking-wider text-blue-600">
                        Step 1 · Vehicle Selection
                    </span>
                    <h2 className="text-lg font-bold text-slate-900">
                        Select Your Vehicle
                    </h2>
                </div>

                <div className="inline-flex rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        onClick={() => setMode('ymm')}
                        className={`rounded-lg px-3.5 py-1.5 text-xs font-semibold transition ${
                            mode === 'ymm'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        Year / Make / Model
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('vin')}
                        className={`rounded-lg px-3.5 py-1.5 text-xs font-semibold transition ${
                            mode === 'vin'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        17-Digit VIN Lookup
                    </button>
                </div>
            </div>

            {mode === 'ymm' ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label
                            htmlFor="vehicle-year"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Year
                        </label>
                        <select
                            id="vehicle-year"
                            value={selectedYear}
                            onChange={(e) => handleYearChange(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option value="">Select Year</option>
                            {years.map((yr) => (
                                <option key={yr} value={yr}>
                                    {yr}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="vehicle-make"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Make
                        </label>
                        <select
                            id="vehicle-make"
                            value={selectedMake}
                            disabled={!selectedYear}
                            onChange={(e) => handleMakeChange(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option value="">Select Make</option>
                            {makes.map((mk) => (
                                <option key={mk} value={mk}>
                                    {mk}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="vehicle-model"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Model
                        </label>
                        <select
                            id="vehicle-model"
                            value={selectedModel}
                            disabled={!selectedMake}
                            onChange={(e) => handleModelChange(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option value="">Select Model</option>
                            {models.map((mdl) => (
                                <option key={mdl} value={mdl}>
                                    {mdl}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="vehicle-trim"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Trim & Engine
                        </label>
                        <select
                            id="vehicle-trim"
                            value={selectedTrimKey}
                            disabled={!selectedModel || trimVariants.length === 0}
                            onChange={(e) => handleTrimChange(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option value="">Select Trim / Engine</option>
                            {trimVariants.map((variant) => {
                                const label = [variant.submodel || 'Base', variant.engine]
                                    .filter(Boolean)
                                    .join(' · ');
                                return (
                                    <option key={variant.id} value={variant.id}>
                                        {label}
                                    </option>
                                );
                            })}
                        </select>
                    </div>
                </div>
            ) : (
                <form onSubmit={handleVinSubmit} className="space-y-3">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <input
                            type="text"
                            maxLength={17}
                            value={vinInput}
                            onChange={(e) =>
                                setVinInput(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))
                            }
                            placeholder="Enter 17-character VIN (e.g., 4T1B11HK5KU123456)"
                            className="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 font-mono text-sm uppercase tracking-wider text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        />
                        <button
                            type="submit"
                            disabled={vinLoading || vinInput.length !== 17}
                            className="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {vinLoading ? 'Decoding via NHTSA...' : 'Decode VIN'}
                        </button>
                    </div>
                    {vinError && (
                        <p className="text-xs font-medium text-red-600" role="alert">
                            {vinError}
                        </p>
                    )}
                    <p className="text-xs text-slate-500">
                        Powered by official US DOT NHTSA vPIC specification decoding.
                    </p>
                </form>
            )}

            {value && (
                <div className="mt-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-2.5 text-xs text-emerald-900">
                    <div>
                        <span className="font-bold">Active Vehicle: </span>
                        {value.year} {value.make} {value.model}
                        {value.submodel ? ` ${value.submodel}` : ''}
                        {value.engine ? ` (${value.engine})` : ''}
                        {value.vin ? ` · VIN: ${value.vin}` : ''}
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            setSelectedYear('');
                            setSelectedMake('');
                            setSelectedModel('');
                            setSelectedTrimKey('');
                            setVinInput('');
                            onChange(null);
                        }}
                        className="font-semibold text-emerald-800 underline hover:text-emerald-950"
                    >
                        Change
                    </button>
                </div>
            )}
        </div>
    );
}
