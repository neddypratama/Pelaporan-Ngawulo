<?php

use App\Models\Barang;
use App\Models\Menu;
use App\Models\Rating;
use App\Models\Transaksi;
use App\Models\BarangKeluar;
use App\Models\BarangMasuk;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Carbon\Carbon;
use Livewire\WithPagination;

new class extends Component {
    use Toast;
    use WithPagination;

    public array $stokChart = [];
    public array $barangMasukChart = [];
    public array $barangKeluarChart = [];
    public array $penjualanChart = [];
    public array $pembelianMenuChart = [];
    public array $ratingChart = [];

    public $selectedMasuk = null; // Selected barang masuk id
    public $selectedKeluar = null; // Selected barang keluar id

    public function mount()
    {
        $this->chartStok();
        $this->chartBarangMasuk();
        $this->chartBarangKeluar();
        $this->chartGross();
        $this->chartPembelianMenu();
        $this->chartRating();
    }

    public function updatedSelectedMasuk()
    {
        $this->chartBarangMasuk();
    }

    public function updatedSelectedKeluar()
    {
        $this->chartBarangKeluar();
    }

    public function chartPembelianMenu()
    {
        $data = \App\Models\Order::selectRaw('menu_id, SUM(qty) as total_qty')
            ->groupBy('menu_id')
            ->with('menu:id,name')
            ->get()
            ->filter(fn($order) => $order->menu) // pastikan relasi menu tidak null
            ->values();

        $labels = $data->pluck('menu.name')->toArray();
        $qtyData = $data->pluck('total_qty')->toArray();

        $generateRandomColor = fn() => '#' . substr(str_shuffle('ABCDEF0123456789'), 0, 6);
        $colors = array_map($generateRandomColor, range(1, count($data)));

        $this->pembelianMenuChart = [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Jumlah Pembelian per Menu',
                        'data' => $qtyData,
                        'backgroundColor' => $colors,
                        'borderColor' => $colors,
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];
    }

    public function chartRating()
    {
        $data = Rating::selectRaw('menu_id, AVG(rating) as rata_rating')
            ->groupBy('menu_id')
            ->with('menu:id,name')
            ->get()
            ->filter(fn($r) => $r->menu) // pastikan relasi menu ada
            ->values();

        $labels = $data->pluck('menu.name')->toArray();
        $ratingData = $data->pluck('rata_rating')->map(fn($val) => round($val, 2))->toArray();

        $generateRandomColor = fn() => '#' . substr(str_shuffle('ABCDEF0123456789'), 0, 6);
        $colors = array_map($generateRandomColor, range(1, count($data)));

        $this->ratingChart = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Rating Rata-Rata',
                        'data' => $ratingData,
                        'backgroundColor' => $colors,
                        'borderColor' => $colors,
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'max' => 5,
                    ],
                ],
            ],
        ];
    }

    public function chartBarangMasuk()
    {
        $query = BarangMasuk::selectRaw('DATE(tanggal) as tanggal, SUM(jumlah) as total_masuk')->groupBy('tanggal')->orderBy('tanggal');

        if ($this->selectedMasuk) {
            $query->where('barang_id', $this->selectedMasuk);
        }

        $data = $query->get();

        $labels = $data->pluck('tanggal')->toArray();
        $jumlahMasuk = $data->pluck('total_masuk')->toArray();

        $this->barangMasukChart = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Barang Masuk',
                        'data' => $jumlahMasuk,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'borderWidth' => 2,
                        'fill' => true,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ];
    }

    public function chartBarangKeluar()
    {
        $query = BarangKeluar::selectRaw('DATE(tanggal) as tanggal, SUM(jumlah) as total_keluar')->groupBy('tanggal')->orderBy('tanggal');

        if ($this->selectedKeluar) {
            $query->where('barang_id', $this->selectedKeluar);
        }

        $data = $query->get();

        $labels = $data->pluck('tanggal')->toArray();
        $jumlahKeluar = $data->pluck('total_keluar')->toArray();

        $this->barangKeluarChart = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Barang Keluar',
                        'data' => $jumlahKeluar,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'borderWidth' => 2,
                        'fill' => true,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ];
    }

    public function chartStok()
    {
        $data = Barang::select('name', 'stok')->orderBy('name')->get();

        $labels = $data->pluck('name')->toArray();
        $stokData = $data->pluck('stok')->toArray();

        $generateRandomColor = fn() => '#' . substr(str_shuffle('ABCDEF0123456789'), 0, 6);
        $colors = array_map($generateRandomColor, range(1, count($data)));

        $this->stokChart = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Stock Barang',
                        'data' => $stokData,
                        'backgroundColor' => $colors,
                        'borderColor' => $colors,
                        'borderWidth' => 1,
                    ],
                ],
            ],
            'options' => ['responsive' => true],
        ];
    }

    public function chartGross()
    {
        $data = Transaksi::where('status', 'success')->orderBy('tanggal')->get()->groupBy(fn($trx) => Carbon::parse($trx->tanggal)->format('Y-m-d'))->map(fn($trx) => $trx->sum('total'))->toArray();

        $this->penjualanChart = [
            'type' => 'line',
            'data' => [
                'labels' => array_keys($data),
                'datasets' => [
                    [
                        'label' => 'Pendapatan',
                        'data' => array_values($data),
                        'borderColor' => '#4F46E5',
                        'backgroundColor' => 'rgba(79, 70, 229, 0.2)',
                    ],
                ],
            ],
        ];
    }

    public function with()
    {
        return [
            'barangs' => Barang::all(),
        ];
    }
};
?>

<div>
    <x-header title="Dashboard" separator progress-indicator />

    @if (auth()->user()->role_id == 4)
        {{-- Role 4 tidak lihat chart --}}
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

            <x-card class="grid col-span-2">
                <x-slot:title>Penjualan</x-slot:title>
                <x-chart wire:model="penjualanChart" />
            </x-card>

            <x-card class="grid col-span-1">
                <x-slot:title>Pembelian Menu</x-slot:title>
                <x-chart wire:model="pembelianMenuChart" />
            </x-card>

            <x-card class="grid col-span-1">
                <x-slot:title>Rating Menu</x-slot:title>
                <x-chart wire:model="ratingChart" />
            </x-card>

            <x-card class="grid col-span-2">
                <x-slot:title>Stok Barang</x-slot:title>
                <x-chart wire:model="stokChart" />
            </x-card>

            <x-card class="grid col-span-1">
                <x-slot:title>Barang Masuk</x-slot:title>
                <x-slot:menu>
                    <x-select wire:model.live="selectedMasuk" :options="$barangs" prefix="Nama Barang"
                        hint="Pilih barang yang diinginkan!" placeholder="-- Semua Barang --" />
                </x-slot:menu>
                <x-chart wire:model.live="barangMasukChart" />
            </x-card>

            <x-card class="grid col-span-1">
                <x-slot:title>Barang Keluar</x-slot:title>
                <x-slot:menu>
                    <x-select wire:model.live="selectedKeluar" :options="$barangs" prefix="Nama Barang"
                        hint="Pilih barang yang diinginkan!" placeholder="-- Semua Barang --" />
                </x-slot:menu>
                <x-chart wire:model.live="barangKeluarChart" />
            </x-card>

        </div>
    @endif
</div>
