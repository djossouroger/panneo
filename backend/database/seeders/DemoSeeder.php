<?php

namespace Database\Seeders;

use App\Models\ArtisanProfile;
use App\Models\ArtisanVerificationDocument;
use App\Models\ArtisanVerificationSubmission;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo123!';

    public function run(): void
    {
        $admin = $this->createUser([
            'name' => 'Démo Admin',
            'email' => 'admin.demo@panneo.test',
            'phone' => '+2290100000001',
            'role' => 'admin',
        ]);

        $client = $this->createUser([
            'name' => 'Démo Client',
            'email' => 'client.demo@panneo.test',
            'phone' => '+2290100000002',
            'role' => 'client',
        ]);

        $artisanUser = $this->createUser([
            'name' => 'Démo Artisan',
            'email' => 'artisan.demo@panneo.test',
            'phone' => '+2290100000003',
            'role' => 'artisan',
        ]);

        $plumbing = Category::where('slug', 'plomberie')->first()
            ?? Category::create([
                'name' => 'Plomberie',
                'slug' => 'plomberie',
                'icon' => 'plumbing',
                'is_active' => true,
            ]);

        $electricity = Category::where('slug', 'electricite')->first();

        $profile = ArtisanProfile::updateOrCreate(
            ['user_id' => $artisanUser->id],
            [
                'category_id' => $plumbing->id,
                'city' => 'Cotonou',
                'district' => 'Akpakpa',
                'description' => 'Dépanneur en plomberie disponible sur Cotonou et environs.',
                'is_available' => true,
                'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'years_of_experience' => 8,
                'specialties' => ['Urgences', 'Sanitaire', 'Pose de robinetterie'],
            ]
        );

        $artisanUser->categories()->sync([
            $plumbing->id => ['is_primary' => true],
            $electricity?->id => ['is_primary' => false],
        ]);

        $artisanUser->serviceAreas()->updateOrCreate(
            ['artisan_id' => $artisanUser->id, 'city' => 'Cotonou', 'district' => 'Akpakpa'],
            []
        );

        $artisanUser->serviceAreas()->updateOrCreate(
            ['artisan_id' => $artisanUser->id, 'city' => 'Cotonou', 'district' => null],
            []
        );

        foreach (range(1, 6) as $day) {
            $artisanUser->workingHours()->updateOrCreate(
                ['artisan_id' => $artisanUser->id, 'day_of_week' => $day],
                ['start_time' => '08:00', 'end_time' => '18:00', 'is_working_day' => true]
            );
        }

        $submission = ArtisanVerificationSubmission::updateOrCreate(
            ['artisan_id' => $artisanUser->id, 'status' => ArtisanVerificationSubmission::STATUS_APPROVED],
            [
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDays(1),
                'reviewed_by' => $admin->id,
            ]
        );

        ArtisanVerificationDocument::updateOrCreate(
            ['submission_id' => $submission->id, 'document_type' => 'identity_document'],
            [
                'file_path' => 'documents/demo_cni.jpg',
                'original_name' => 'cni_demo.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
            ]
        );

        ArtisanVerificationDocument::updateOrCreate(
            ['submission_id' => $submission->id, 'document_type' => 'selfie'],
            [
                'file_path' => 'documents/demo_selfie.jpg',
                'original_name' => 'selfie_demo.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
            ]
        );
    }

    private function createUser(array $attributes): User
    {
        return User::updateOrCreate(
            ['email' => $attributes['email']],
            [
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => $attributes['role'],
                'is_active' => true,
            ]
        );
    }
}

