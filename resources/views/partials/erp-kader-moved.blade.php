{{-- Mitra kader dikelola NUMART ERP (2026-11-20). $audience: 'kader' (panel kader) | 'admin' (Backoffice toko) --}}
@php($erpBackoffice = rtrim((string) config('services.numart_erp.url'), '/').'/backoffice')
<div class="panel" style="border-left:4px solid #0f7a4a;margin-bottom:16px">
    <strong>Mitra kader kini dikelola di Backoffice NUMART ERP.</strong>
    @if(($audience ?? 'admin') === 'kader')
        <p style="margin:6px 0 0">Mitra binaan, pendaftaran mitra baru, dan komisi Anda ada di menu <strong>Kader Saya</strong>. Akun login diberikan admin.</p>
    @else
        <p style="margin:6px 0 0">Angkat kader, daftar mitra binaan, tarif dan pembayaran komisi ada di <strong>Keuangan → Mitra Kader</strong>. Komisi tercatat sebagai beban &amp; utang komisi di buku besar ERP. Halaman di sini hanya untuk melihat &amp; melunasi komisi lama.</p>
    @endif
    <p style="margin:8px 0 0"><a class="btn" href="{{ $erpBackoffice }}" target="_blank" rel="noopener">Buka Backoffice ERP</a></p>
</div>
