<?php

namespace App\Livewire;

use App\Models\Barang;
use App\Models\BarangKeluar;
use App\Models\BarangMasuk;

use App\Models\Transaksi;
use App\Models\Order;
use App\Models\Rating;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Carbon\Carbon;

new class extends Component {
    use Toast;

    public string $period = 'month';
    public $startDate;
    public $endDate;

    public array $myChart = [];
    public array $categoryChart = [];
    public array $ratingChart = [];

    public array $stokChart = [];
    public array $barangMasukChart = [];
    public array $barangKeluarChart = [];

    public $selectedMasuk = null; // Menyimpan ID barang yang dipilih
    public $selectedKeluar = null; // Menyimpan ID barang yang dipilih

    public function mount()
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->setDefaultDates();

        $this->chartGross();
        $this->chartCategories();
        $this->chartAverageRating();

        $this->chartStok();
        $this->chartBarangMasuk();
        $this->chartBarangKeluar();
    }

    protected function setDefaultDates()
    {
        $now = Carbon::now();

        switch ($this->period) {
            case 'today':
                $this->startDate = $now->copy()->startOfDay();
                $this->endDate = $now->copy()->endOfDay();
                break;
            case 'week':
                $this->startDate = $now->copy()->startOfWeek();
                $this->endDate = $now->copy()->endOfWeek();
                break;
            case 'month':
                $this->startDate = $now->copy()->startOfMonth();
                $this->endDate = $now->copy()->endOfMonth();
                break;
            case 'year':
                $this->startDate = $now->copy()->startOfYear();
                $this->endDate = $now->copy()->endOfYear();
                break;
            default:
                $this->startDate = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : $now->copy()->startOfMonth();
                $this->endDate = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : $now->copy()->endOfMonth();
        }
    }

    public function updatedPeriod()
    {
        $this->setDefaultDates();
        $this->chartGross();
        $this->chartCategories();
        $this->chartAverageRating();
    }

    public function applyDateRange()
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $this->period = 'custom';
        $this->startDate = Carbon::parse($this->startDate)->startOfDay();
        $this->endDate = Carbon::parse($this->endDate)->endOfDay();

        $this->chartGross();
        $this->chartCategories();
        $this->chartAverageRating();
        $this->toast('Periode tanggal berhasil diperbarui', 'success');
    }

    public function chartGross()
    {
        $data = Transaksi::whereNotIn('status', ['pending', 'cancel'])
            ->whereBetween('tanggal', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->orderBy('tanggal')
            ->get()
            ->groupBy(fn($trx) => Carbon::parse($trx->tanggal)->format('Y-m-d'))
            ->map(fn($trx) => $trx->sum('total'))
            ->toArray();

        $this->myChart = [
            'type' => 'line',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [
                    [
                        'label' => 'Hasil Pendapatan',
                        'data' => array_values($data),
                        'borderColor' => '#4F46E5',
                        'backgroundColor' => 'rgba(79, 70, 229, 0.2)',
                    ],
                ],
            ],
        ];
    }

    public function chartCategories()
    {
        $data = Order::join('menus', 'orders.menu_id', '=', 'menus.id')
            ->join('transaksis', 'orders.transaksi_id', '=', 'transaksis.id')
            ->whereNotIn('transaksis.status', ['pending', 'cancel'])
            ->selectRaw('menus.name, SUM(orders.qty) as total_qty')
            ->whereBetween('orders.created_at', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->groupBy('menus.name')
            ->get();

        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4CAF50', '#9C27B0', '#F44336', '#E91E63', '#03A9F4', '#009688', '#FF9800'];

        // Ambil labels dan data dari Collection $data
        $labels = $data->pluck('name')->toArray();
        $values = $data->pluck('total_qty')->toArray();

        $this->categoryChart = [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Total Kuantitas per Menu',
                        'data' => $values,
                        'backgroundColor' => array_slice($colors, 0, count($values)),
                    ],
                ],
            ],
        ];
    }

    public function chartAverageRating()
    {
        // Ambil data rata-rata rating per menu dari tabel ratings
        $data = Rating::selectRaw('menu_id, AVG(rating) as average_rating')
            ->groupBy('menu_id')
            ->with('menu:id,name') // Pastikan relasi menu dimuat
            ->whereBetween('created_at', [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()])
            ->get()
            ->filter(fn($r) => $r->menu) // Filter yang punya relasi menu
            ->values();

        // Ambil label (nama menu) dan nilai rating rata-rata
        $labels = $data->pluck('menu.name')->toArray();
        $averageRatings = $data->pluck('average_rating')->map(fn($val) => round($val, 2))->toArray();

        // Warna chart, bisa random atau preset
        $colors = [];
        for ($i = 0; $i < count($averageRatings); $i++) {
            $colors[] = sprintf('#%06X', mt_rand(0, 0xffffff));
        }

        // Siapkan data chart
        $this->ratingChart = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Rata-Rata Rating per Menu',
                        'data' => $averageRatings,
                        'backgroundColor' => $colors,
                        'borderColor' => $colors,
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
            ],
        ];
    }

    public function updatedSelectedMasuk()
    {
        $this->chartBarangMasuk(); // Memanggil ulang method untuk memperbarui chart
    }

    public function updatedSelectedKeluar()
    {
        $this->chartBarangKeluar(); // Memanggil ulang method untuk memperbarui chart
    }

    public function chartBarangMasuk()
    {
        // Debugging untuk memastikan filter bekerja
        logger('selectedMasuk: ' . json_encode($this->selectedMasuk));

        // Jika tidak ada barang yang dipilih, tampilkan semua barang masuk
        $query = BarangMasuk::selectRaw('DATE(tanggal) as tanggal, SUM(jumlah) as total_masuk')->groupBy('tanggal')->orderBy('tanggal');

        // Jika ada barang yang dipilih, filter berdasarkan barang_id
        if ($this->selectedMasuk) {
            $query->where('barang_id', $this->selectedMasuk); // Gunakan selectedMasuk untuk filter
        }

        $data = $query->get();

        // Format data untuk chart
        $labels = $data->pluck('tanggal')->toArray(); // Mengambil tanggal sebagai label
        $jumlahMasuk = $data->pluck('total_masuk')->toArray(); // Mengambil jumlah barang masuk sebagai data chart

        // Fungsi untuk menghasilkan warna acak
        $generateRandomColor = fn() => '#' . substr(str_shuffle('ABCDEF0123456789'), 0, 6);

        // Menghasilkan warna acak untuk chart
        $colors = array_map($generateRandomColor, range(1, count($data)));

        // Mengatur data chart untuk Barang Masuk
        $this->barangMasukChart = [
            'type' => 'line', // Jenis chart adalah line
            'data' => [
                'labels' => $labels, // Label adalah tanggal
                'datasets' => [
                    [
                        'label' => 'Barang Masuk',
                        'data' => $jumlahMasuk, // Data jumlah barang masuk
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)', // Warna background
                        'borderColor' => 'rgba(75, 192, 192, 1)', // Warna border
                        'borderWidth' => 2, // Ketebalan border
                        'fill' => true, // Mengisi area di bawah garis
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];
    }

    public function chartBarangKeluar()
    {
        // Jika tidak ada barang yang dipilih, tampilkan semua barang keluar
        $query = BarangKeluar::selectRaw('DATE(tanggal) as tanggal, SUM(jumlah) as total_keluar')->groupBy('tanggal')->orderBy('tanggal');

        // Jika ada barang yang dipilih, filter berdasarkan barang_id
        if ($this->selectedKeluar) {
            $query->where('barang_id', $this->selectedKeluar); // Gunakan selectedKeluar untuk filter
        }

        $data = $query->get();

        // Format data untuk chart
        $labels = $data->pluck('tanggal')->toArray(); // Mengambil tanggal sebagai label
        $jumlahKeluar = $data->pluck('total_keluar')->toArray(); // Mengambil jumlah barang keluar sebagai data chart

        // Fungsi untuk menghasilkan warna acak
        $generateRandomColor = fn() => '#' . substr(str_shuffle('ABCDEF0123456789'), 0, 6);

        // Menghasilkan warna acak untuk chart
        $colors = array_map($generateRandomColor, range(1, count($data)));

        // Mengatur data chart untuk Barang Keluar
        $this->barangKeluarChart = [
            'type' => 'line', // Jenis chart adalah line
            'data' => [
                'labels' => $labels, // Label adalah tanggal
                'datasets' => [
                    [
                        'label' => 'Barang Keluar',
                        'data' => $jumlahKeluar, // Data jumlah barang keluar
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)', // Warna background
                        'borderColor' => 'rgba(255, 99, 132, 1)', // Warna border
                        'borderWidth' => 2, // Ketebalan border
                        'fill' => true, // Mengisi area di bawah garis
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];
    }

    public function chartStok()
    {
        // Ambil data barang dan stoknya
        $data = Barang::select('name', 'stok')
            ->orderBy('name') // Menyusun berdasarkan nama barang
            ->get();

        // Menyusun data untuk chart
        $labels = $data->pluck('name')->toArray(); // Ambil nama barang untuk label
        $stokData = $data->pluck('stok')->toArray(); // Ambil stok untuk data chart

        // Fungsi untuk menghasilkan warna acak
        $generateRandomColor = fn() => '#' . substr(str_shuffle('ABCDEF0123456789'), 0, 6);

        // Menghasilkan array warna acak untuk setiap barang
        $colors = array_map($generateRandomColor, range(1, count($data)));

        // Mengatur data chart
        $this->stokChart = [
            'type' => 'bar', // Jenis chart bar
            'data' => [
                'labels' => $labels, // Menggunakan nama barang sebagai label
                'datasets' => [
                    [
                        'label' => 'Stock Barang',
                        'data' => $stokData, // Menggunakan stok sebagai data
                        'backgroundColor' => $colors, // Warna background batang
                        'borderColor' => $colors, // Warna border batang
                        'borderWidth' => 1, // Ketebalan border
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
            ],
        ];
    }

    public function with()
    {
        return [
            'barangs' => Barang::all(),
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }
};
?>

<div class="">
    <x-header title="Dashboard" separator progress-indicator>
        <x-slot:actions>
            @php
                $periods = [
                    [
                        'id' => 'today',
                        'name' => 'Hari Ini',
                        'hint' => 'Data dalam 24 jam terakhir',
                        'icon' => 'o-clock',
                    ],
                    [
                        'id' => 'week',
                        'name' => 'Minggu Ini',
                        'hint' => 'Data minggu berjalan',
                        'icon' => 'o-calendar-days',
                    ],
                    [
                        'id' => 'month',
                        'name' => 'Bulan Ini',
                        'hint' => 'Data bulan berjalan',
                        'icon' => 'o-chart-pie',
                    ],
                    [
                        'id' => 'year',
                        'name' => 'Tahun Ini',
                        'hint' => 'Data tahun berjalan',
                        'icon' => 'o-chart-bar',
                    ],
                    [
                        'id' => 'custom',
                        'name' => 'Custom',
                        'hint' => 'Pilih rentang tanggal khusus',
                        'icon' => 'o-calendar',
                    ],
                ];
            @endphp

            @if (auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
                <div class="flex flex-col gap-4">
                    <x-select wire:model.live="period" :options="$periods" option-label="name" option-value="id"
                        option-description="hint" class="gap-4">
                    </x-select>

                    @if ($period === 'custom')
                        <div class="flex flex-col gap-4 mt-2">
                            <form wire:submit.prevent="applyDateRange">
                                <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                                    <x-input type="date" label="Dari Tanggal" wire:model="startDate"
                                        :max="now()->format('Y-m-d')" class="w-full md:w-auto" />

                                    <x-input type="date" label="Sampai Tanggal" wire:model="endDate"
                                        :min="$startDate" :max="now()->format('Y-m-d')" class="w-full md:w-auto" />

                                    <x-button spinner label="Terapkan" type="submit" icon="o-check"
                                        class="btn-primary mt-2 md:mt-6 w-full md:w-auto" />
                                </div>

                                @error('endDate')
                                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                                @enderror

                                <div class="text-sm text-gray-500 mt-2">
                                    Periode terpilih:
                                    {{ $startDate->translatedFormat('d M Y') }} -
                                    {{ $endDate->translatedFormat('d M Y') }}
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        </x-slot:actions>
    </x-header>

    @if (auth()->user()->role_id == 1 || auth()->user()->role_id == 2)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <x-card class="grid col-span-2">
                <x-slot:title>Pendapatan Penjualan</x-slot:title>
                <x-chart wire:model="myChart" />
            </x-card>

            <x-card class="grid col-span-1">
                <x-slot:title>Penjualan Menu</x-slot:title>
                <x-chart wire:model="categoryChart" />
            </x-card>
            <x-card class="grid col-span-1">
                <x-slot:title>Rating Menu</x-slot:title>
                <x-chart wire:model="ratingChart" />
            </x-card>
            <x-card class="grid col-span-2">
                <x-slot:title>Stok Barang</x-slot:title>
                <x-chart wire:model="stokChart" />
            </x-card>

            <!-- Barang Masuk Chart -->
            <x-card class="grid col-span-1">
                <x-slot:title>Barang Masuk</x-slot:title>
                <x-slot:menu>
                    <x-select wire:model.live="selectedMasuk" :options="$barangs" prefix="Nama Barang"
                        hint="Pilih barang yang diinginkan!" placeholder="-- Semua Barang --" />
                </x-slot:menu>
                <x-chart wire:model.live="barangMasukChart" />
            </x-card>

            <!-- Barang Keluar Chart -->
            <x-card class="grid col-span-1">
                <x-slot:title>Barang Keluar</x-slot:title>
                <x-slot:menu>
                    <x-select wire:model.live="selectedKeluar" :options="$barangs" prefix="Nama Barang"
                        hint="Pilih barang yang diinginkan!" placeholder="-- Semua Barang --" />
                </x-slot:menu>
                <x-chart wire:model="barangKeluarChart" />
            </x-card>
        </div>
    @endif
</div>
