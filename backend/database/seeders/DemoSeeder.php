<?php

namespace Database\Seeders;

use App\Models\ArtisanProfile;
use App\Models\ArtisanVerificationDocument;
use App\Models\ArtisanVerificationSubmission;
use App\Models\Category;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        foreach (range(0, 6) as $day) {
            $artisanUser->workingHours()->updateOrCreate(
                ['artisan_id' => $artisanUser->id, 'day_of_week' => $day],
                ['start_time' => '00:00', 'end_time' => '23:59', 'is_working_day' => true]
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

        $this->createPendingArtisan();

        $this->createDemoRequests($client, $artisanUser, $plumbing, $electricity);

        $this->createCityClusters($admin);
    }

    /**
     * Pour chaque ville, un client démo et deux artisans VALIDÉS disponibles
     * 7j/7 24h couvrant l'ensemble des catégories : quel que soit le compte
     * client utilisé et sa ville, le matching trouve au moins un artisan.
     *
     * Les deux artisans d'une même ville couvrent les 5 catégories :
     *  - artisan A : plomberie (primaire) + électricité + électroménager
     *  - artisan B : climatisation (primaire) + serrurerie
     */
    private function createCityClusters(User $admin): void
    {
        $cities = ['Cotonou', 'Akpakpa', 'Calavi', 'Porto-Novo', 'Parakou', 'Ouidah', 'Bohicon', 'Abomey'];

        $districts = [
            'Cotonou' => 'Akpakpa',
            'Akpakpa' => 'Akpakpa',
            'Calavi' => 'Calavi',
            'Porto-Novo' => 'Porto-Novo',
            'Parakou' => 'Parakou',
            'Ouidah' => 'Ouidah',
            'Bohicon' => 'Bohicon',
            'Abomey' => 'Abomey',
        ];

        $phoneIndex = 100;
        $adminId = $admin->id;

        foreach ($cities as $city) {
            $slug = Str::slug($city);
            $district = $districts[$city] ?? $city;

            $client = $this->createUser([
                'name' => "Démo Client $city",
                'email' => "client.$slug.demo@panneo.test",
                'phone' => '+2290100000'.($phoneIndex++),
                'role' => 'client',
            ]);

            $artisanA = $this->createUser([
                'name' => "Démo Artisan Plomberie $city",
                'email' => "artisan.plomberie.$slug.demo@panneo.test",
                'phone' => '+2290100000'.($phoneIndex++),
                'role' => 'artisan',
            ]);

            $artisanB = $this->createUser([
                'name' => "Démo Artisan Climatisation $city",
                'email' => "artisan.climatisation.$slug.demo@panneo.test",
                'phone' => '+2290100000'.($phoneIndex++),
                'role' => 'artisan',
            ]);

            $this->createVerifiedArtisan($artisanA, $city, $district, $adminId, 'plomberie', ['plomberie', 'electricite', 'electromenager'], 'Dépanneur en plomberie disponible sur '.$city.' et environs.');
            $this->createVerifiedArtisan($artisanB, $city, $district, $adminId, 'climatisation', ['climatisation', 'serrurerie'], 'Dépanneur en climatisation disponible sur '.$city.' et environs.');

            // Une demande en attente pour chaque client de ville, afin de
            // démontrer la recherche de dépanneurs dans sa propre ville.
            $this->createRequest([
                'reference' => "PAN-2026-$city-DEMO",
                'client_id' => $client->id,
                'category_id' => Category::where('slug', 'plomberie')->value('id'),
                'title' => 'Besoins de dépannage — '.$city,
                'description' => 'Demande de démonstration créée depuis le compte client de '.$city.'.',
                'city' => $city,
                'district' => $district,
                'address_details' => 'Quartier central de '.$city,
                'status' => RepairRequest::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Artisan validé, disponible 7j/7 24h, couvrant toute sa ville
     * (service area sans district = toute la ville).
     */
    private function createVerifiedArtisan(User $user, string $city, string $district, int $adminId, string $primarySlug, array $categorySlugs, string $description): void
    {
        $primary = Category::where('slug', $primarySlug)->first();

        ArtisanProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'category_id' => $primary?->id,
                'city' => $city,
                'district' => $district,
                'description' => $description,
                'is_available' => true,
                'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
                'verified_at' => now(),
                'verified_by' => $adminId,
                'years_of_experience' => rand(3, 12),
                'specialties' => ['Urgences', 'Dépannage à domicile'],
            ]
        );

        $categoryMap = collect($categorySlugs)
            ->mapWithKeys(fn (string $slug, int $index) => [
                Category::where('slug', $slug)->value('id') => ['is_primary' => $index === 0],
            ])
            ->filter(fn ($value, $key) => $key !== null)
            ->all();

        if ($categoryMap !== []) {
            $user->categories()->sync($categoryMap);
        }

        // Zone de service : toute la ville (district null = ville entière).
        $user->serviceAreas()->updateOrCreate(
            ['artisan_id' => $user->id, 'city' => $city, 'district' => null],
            []
        );

        // Horaires 7j/7 24h : le matching fonctionne à tout moment.
        foreach (range(0, 6) as $day) {
            $user->workingHours()->updateOrCreate(
                ['artisan_id' => $user->id, 'day_of_week' => $day],
                ['start_time' => '00:00', 'end_time' => '23:59', 'is_working_day' => true]
            );
        }

        $submission = ArtisanVerificationSubmission::updateOrCreate(
            ['artisan_id' => $user->id, 'status' => ArtisanVerificationSubmission::STATUS_APPROVED],
            [
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDays(1),
                'reviewed_by' => $adminId,
            ]
        );

        ArtisanVerificationDocument::updateOrCreate(
            ['submission_id' => $submission->id, 'document_type' => 'identity_document'],
            [
                'file_path' => 'documents/demo_cni.jpg',
                'original_name' => 'cni_'.$user->id.'.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
            ]
        );

        ArtisanVerificationDocument::updateOrCreate(
            ['submission_id' => $submission->id, 'document_type' => 'selfie'],
            [
                'file_path' => 'documents/demo_selfie.jpg',
                'original_name' => 'selfie_'.$user->id.'.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
            ]
        );
    }

    /**
     * Deuxième artisan, en attente de validation, pour démontrer le
     * circuit de vérification côté back-office.
     */
    private function createPendingArtisan(): void
    {
        $pendingUser = $this->createUser([
            'name' => 'Démo Artisan 2 (à valider)',
            'email' => 'artisan2.pending@panneo.test',
            'phone' => '+2290100000004',
            'role' => 'artisan',
        ]);

        $electricity = Category::where('slug', 'electricite')->first();

        $profile = ArtisanProfile::updateOrCreate(
            ['user_id' => $pendingUser->id],
            [
                'category_id' => $electricity?->id,
                'city' => 'Cotonou',
                'district' => 'Haie Vive',
                'description' => 'Électricien en attente de validation par Pannéo.',
                'is_available' => false,
                'verification_status' => ArtisanProfile::VERIFICATION_PENDING,
                'years_of_experience' => 5,
                'specialties' => ['Câblage', 'Prises et interrupteurs'],
            ]
        );

        if ($electricity) {
            $pendingUser->categories()->sync([
                $electricity->id => ['is_primary' => true],
            ]);
        }

        $pendingUser->serviceAreas()->updateOrCreate(
            ['artisan_id' => $pendingUser->id, 'city' => 'Cotonou', 'district' => 'Haie Vive'],
            []
        );

        $submission = ArtisanVerificationSubmission::updateOrCreate(
            ['artisan_id' => $pendingUser->id, 'status' => ArtisanVerificationSubmission::STATUS_PENDING],
            [
                'submitted_at' => now()->subHours(2),
            ]
        );

        ArtisanVerificationDocument::updateOrCreate(
            ['submission_id' => $submission->id, 'document_type' => 'identity_document'],
            [
                'file_path' => 'documents/demo_cni.jpg',
                'original_name' => 'cni_artisan2.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
            ]
        );

        ArtisanVerificationDocument::updateOrCreate(
            ['submission_id' => $submission->id, 'document_type' => 'selfie'],
            [
                'file_path' => 'documents/demo_selfie.jpg',
                'original_name' => 'selfie_artisan2.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
            ]
        );

        Notification::firstOrCreate(
            [
                'user_id' => $pendingUser->id,
                'type' => Notification::TYPE_ARTISAN_ACCOUNT_VERIFIED,
            ],
            [
                'title' => 'Dossier en cours de vérification',
                'message' => 'Votre dossier d’identité est en cours de vérification par Pannéo.',
                'data' => null,
            ]
        );
    }

    /**
     * Quelques demandes de dépannage dans des états différents pour
     * démontrer le parcours client, artisan et admin.
     */
    private function createDemoRequests(User $client, User $artisan, ?Category $plumbing, ?Category $electricity): void
    {
        $now = now();

        // 1) Demande en attente : le client peut chercher des dépanneurs.
        $this->createRequest([
            'reference' => 'PAN-2026-DEMO001',
            'client_id' => $client->id,
            'category_id' => $plumbing?->id,
            'title' => 'Fuite d’eau sous l’évier de la cuisine',
            'description' => 'Une fuite continue sous l’évier de la cuisine. Le robinet goutte en permanence et l’eau stagne dans le meuble.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'address_details' => 'Rue 100, quartier Akpakpa, non loin du marché',
            'status' => RepairRequest::STATUS_PENDING,
        ]);

        // 2) Demande envoyée à l'artisan démo (offre en attente) : l'artisan
        //    peut l'accepter pendant la démo.
        $request2 = $this->createRequest([
            'reference' => 'PAN-2026-DEMO002',
            'client_id' => $client->id,
            'category_id' => $plumbing?->id,
            'title' => 'Chasse d’eau qui fuit dans les toilettes',
            'description' => 'La chasse d’eau fuit en continu. Le réservoir ne se remplit pas correctement.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'address_details' => 'Immeuble Etoile, appartement 12',
            'status' => RepairRequest::STATUS_AWAITING_ARTISAN,
        ]);

        RepairRequestOffer::updateOrCreate(
            [
                'repair_request_id' => $request2->id,
                'artisan_id' => $artisan->id,
            ],
            [
                'status' => RepairRequestOffer::STATUS_PENDING,
            ]
        );

        Notification::firstOrCreate(
            [
                'user_id' => $artisan->id,
                'type' => Notification::TYPE_REPAIR_REQUEST_RECEIVED,
            ],
            [
                'title' => 'Nouvelle demande de dépannage',
                'message' => sprintf('Une nouvelle demande de %s vous a été envoyée à Akpakpa, Cotonou.', $plumbing?->name ?? 'dépannage'),
                'data' => [
                    'repair_request_id' => $request2->id,
                    'reference' => $request2->reference,
                ],
            ]
        );

        // 3) Intervention terminée + avis : historique du client, notation.
        $request3 = $this->createRequest([
            'reference' => 'PAN-2026-DEMO003',
            'client_id' => $client->id,
            'category_id' => $electricity?->id ?? $plumbing?->id,
            'title' => 'Prise électrique à remplacer dans le salon',
            'description' => 'Une prise murale du salon ne fonctionne plus depuis plusieurs jours.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'address_details' => 'Maison à étage, rue des Palmiers',
            'status' => RepairRequest::STATUS_COMPLETED,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => $now->copy()->subDays(6),
            'started_at' => $now->copy()->subDays(5),
            'completed_at' => $now->copy()->subDays(4),
        ]);

        RepairRequestOffer::updateOrCreate(
            [
                'repair_request_id' => $request3->id,
                'artisan_id' => $artisan->id,
            ],
            [
                'status' => RepairRequestOffer::STATUS_ACCEPTED,
                'responded_at' => $now->copy()->subDays(6),
            ]
        );

        Review::updateOrCreate(
            ['repair_request_id' => $request3->id],
            [
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'rating' => 4,
                'comment' => 'Intervention rapide et propre, artisan à l’écoute.',
            ]
        );

        Notification::firstOrCreate(
            [
                'user_id' => $client->id,
                'type' => Notification::TYPE_REPAIR_REQUEST_COMPLETED,
            ],
            [
                'title' => 'Dépannage terminé',
                'message' => 'Votre intervention sur « Prise électrique à remplacer dans le salon » a été terminée.',
                'data' => [
                    'repair_request_id' => $request3->id,
                    'reference' => $request3->reference,
                ],
            ]
        );
    }

    private function createRequest(array $attributes): RepairRequest
    {
        $data = array_merge([
            'accepted_artisan_id' => null,
            'accepted_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'images' => null,
        ], $attributes);

        $request = RepairRequest::updateOrCreate(
            ['reference' => $attributes['reference']],
            $data
        );

        return $request;
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
