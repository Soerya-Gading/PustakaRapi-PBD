// Fitur cari dan urutkan data buku
const cari = document.getElementById('cari');
const urut = document.getElementById('urut');
const isiTabel = document.getElementById('isi-tabel');

function tampilkanBuku() {
    if (!isiTabel) return;
    const kata = cari.value.trim().toLowerCase();
    const baris = Array.from(isiTabel.querySelectorAll('.baris'));

    baris.sort((a, b) => {
        if (urut.value === 'judul') return a.dataset.judul.localeCompare(b.dataset.judul);
        if (urut.value === 'harga') return a.dataset.harga - b.dataset.harga;
        return a.dataset.id - b.dataset.id;
    });

    let tampil = 0;
    const kosong = document.getElementById('baris-kosong');
    
    baris.forEach(tr => {
        tr.hidden = !tr.dataset.judul.toLowerCase().includes(kata);
        if (!tr.hidden) tampil++;
        isiTabel.insertBefore(tr, kosong);
    });

    if (baris.length > 0) {
        kosong.hidden = tampil > 0;
        kosong.firstElementChild.textContent = 'Data buku tidak ditemukan.';
    }
}

if (cari) {
    cari.addEventListener('input', tampilkanBuku);
    urut.addEventListener('change', tampilkanBuku);
}

// Fitur Toast (Notifikasi pojok)
const toast = document.getElementById('toast');
let waktuToast;

function tampilToast(teks) {
    toast.textContent = teks;
    toast.hidden = false;
    clearTimeout(waktuToast);
    waktuToast = setTimeout(() => { toast.hidden = true; }, 3000);
}

// Handle Modal & Tombol Aksi (CUD)
const dialogForm = document.getElementById('dialog-form');
const dialogHapus = document.getElementById('dialog-hapus');
let aksiAktif = '';

document.querySelectorAll('[data-aksi]').forEach(tombol => {
    tombol.addEventListener('click', () => {
        const baris = tombol.closest('.baris');
        aksiAktif = tombol.dataset.aksi;

        // Buka modal hapus
        if (aksiAktif === 'hapus') {
            document.getElementById('teks-hapus').textContent = 'Yakin ingin menghapus buku "' + baris.dataset.judul + '"?';
            dialogHapus.showModal();
            return;
        }

        // Siapkan data form untuk modal tambah/ubah
        const judul = document.getElementById('f-judul');
        const satuan = document.getElementById('f-satuan');
        const harga = document.getElementById('f-harga');
        const status = document.getElementById('f-status');

        if (aksiAktif === 'ubah') {
            document.getElementById('judul-form').textContent = 'Ubah Buku';
            judul.value = baris.dataset.judul;
            harga.value = baris.dataset.harga;
            status.value = baris.dataset.status;
            Array.from(satuan.options).forEach(o => {
                if (o.textContent === baris.dataset.satuan) satuan.value = o.value;
            });
        } else {
            document.getElementById('judul-form').textContent = 'Tambah Buku';
            document.getElementById('form-buku').reset();
        }
        
        dialogForm.showModal();
    });
});

// Tutup modal
document.querySelectorAll('[data-tutup]').forEach(tombol => {
    tombol.addEventListener('click', () => tombol.closest('dialog').close());
});

// TODO: Proses form ke backend PHP menyusul. 
// Sementara tampilkan notif UI saja kalau tombol sudah bisa diklik
const formBuku = document.getElementById('form-buku');
if (formBuku) {
    formBuku.addEventListener('submit', () => {
        const nama = aksiAktif === 'ubah' ? 'Ubah' : 'Tambah';
        tampilToast(`Aksi ${nama} work`);
    });
}

const formHapus = document.getElementById('form-hapus');
if (formHapus) {
    formHapus.addEventListener('submit', () => {
        tampilToast('Aksi Hapus work');
    });
}