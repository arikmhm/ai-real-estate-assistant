<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_lists_all_active_sales_but_only_approved_contacts_have_links(): void
    {
        $first = Listing::factory()->create(['publication_status' => 'published']);
        $second = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'reserved']);
        User::factory()->create(['name' => 'Alya Puspitasari', 'whatsapp_number' => '+6281234567890', 'contact_email' => 'alya@example.test', 'public_contact_approved' => true]);
        User::factory()->create(['name' => 'Bagas Pradana', 'whatsapp_number' => null, 'public_contact_approved' => false]);
        User::factory()->create(['name' => 'Sales Nonaktif', 'is_active' => false, 'whatsapp_number' => '+628111111111', 'public_contact_approved' => true]);
        User::factory()->create(['name' => 'Admin Internal', 'role' => 'admin', 'public_contact_approved' => true]);

        foreach ([$first, $second] as $listing) {
            $this->get('/properti/'.$listing->slug)->assertOk()
                ->assertSee('Alya Puspitasari')->assertSee('Bagas Pradana')
                ->assertDontSee('Sales Nonaktif')->assertDontSee('Admin Internal')
                ->assertSee('wa.me/6281234567890', false)
                ->assertSee('alya@example.test')
                ->assertSee(rawurlencode($listing->code), false)
                ->assertSee(rawurlencode($listing->title), false)
                ->assertSee(rawurlencode(route('properties.show', $listing)), false);
        }
    }

    public function test_home_lists_active_sales_with_generic_whatsapp_message_and_counts(): void
    {
        Listing::factory()->create(['publication_status' => 'published', 'city' => 'Kudus', 'type' => 'rumah']);
        Listing::factory()->create(['publication_status' => 'published', 'city' => 'Jepara', 'type' => 'tanah', 'title' => 'Lahan Kosong Jepara', 'price_idr' => 300000000, 'land_area_m2' => 150]);
        Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'sold', 'city' => 'Pati']);
        User::factory()->create(['name' => 'Alya Puspitasari', 'whatsapp_number' => '+6281234567890', 'public_contact_approved' => true, 'avatar_path' => 'images/sales/avatar-04.webp']);
        User::factory()->create(['name' => 'Sales Nonaktif', 'is_active' => false, 'whatsapp_number' => '+628111111111', 'public_contact_approved' => true]);
        User::factory()->create(['name' => 'Sales Belum Disetujui', 'whatsapp_number' => '+6281299998888', 'public_contact_approved' => false]);

        $this->get('/')->assertOk()
            ->assertSee('+62 812-3456-7890')
            ->assertSee('Sales Belum Disetujui')->assertDontSee('9999')
            ->assertSee(asset('images/sales/avatar-04.webp'), false)
            ->assertSee(asset('images/home/hero.webp'), false)
            ->assertSee('content="'.asset('images/og-default.jpg').'"', false)
            ->assertSee('Alya Puspitasari')->assertDontSee('Sales Nonaktif')
            ->assertSee('wa.me/6281234567890?text='.rawurlencode('Halo, saya ingin bertanya tentang properti di '.config('app.name').'.'), false)
            ->assertSeeInOrder(['Properti tersedia', '>2</dd>', 'Kabupaten/kota', '>2</dd>'], false)
            ->assertSeeInOrder(['Penawaran lahan kosong', 'Lahan Kosong Jepara', 'Rp 300 jt', 'Harga per m²', 'Rp 2.000 rb']);
    }

    public function test_detail_has_clear_empty_state_without_active_sales(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $this->get('/properti/'.$listing->slug)->assertOk()->assertSee('Belum ada sales yang dapat dihubungi');
    }
}
