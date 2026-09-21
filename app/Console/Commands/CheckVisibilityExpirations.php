<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Advertisement;
use App\Models\Product;
use App\Models\Notification;

class CheckVisibilityExpirations extends Command
{
    protected $signature = 'visibility:check-expirations';

    protected $description =
        'Notify users when advertisement or product visibility expires';

    public function handle()
    {
        $this->checkAdvertisements();

        $this->checkProducts();

        $this->info(
            'Visibility expiration check completed.'
        );

        return self::SUCCESS;
    }
 
    private function checkAdvertisements()
    {
        $advertisements = Advertisement::query()

            ->where(
                'visibility_unlocked',
                true
            )

            ->whereNotNull(
                'visibility_expires_at'
            )

            ->where(
                'visibility_expires_at',
                '<=',
                now()
            )

            ->whereNull(
                'visibility_expiration_notified_at'
            )

            ->get();

        foreach ($advertisements as $advertisement) {

            $title = $advertisement->title
                ?? 'Your advertisement';
 
            if ($advertisement->user_id) {

                Notification::create([

                    'user_id' =>
                        $advertisement->user_id,

                    'type' =>
                        'advertisement_visibility_expired',

                    'data' =>
                        json_encode([

                            'advertisement_id' =>
                                $advertisement->id,

                            'title' =>
                                $title,

                        ]),

                    'redirect_url' =>
                        '/advertisement',
                ]);
            }
 
            $advertisement->update([

                'visibility_expiration_notified_at' =>
                    now(),

            ]);
        }

        $this->info(
            $advertisements->count() .
            ' advertisement expiration notification(s) created.'
        );
    }
 
    private function checkProducts()
    {
        $products = Product::query()

            ->where(
                'visibility_unlocked',
                true
            )

            ->whereNotNull(
                'visibility_expires_at'
            )

            ->where(
                'visibility_expires_at',
                '<=',
                now()
            )

            ->whereNull(
                'visibility_expiration_notified_at'
            )

            ->get();

        foreach ($products as $product) {
 
            $name = $product->name
                ?? $product->title
                ?? 'Your product';
 
            if ($product->user_id) {

                Notification::create([

                    'user_id' =>
                        $product->user_id,

                    'type' =>
                        'product_visibility_expired',

                    'data' =>
                        json_encode([

                            'product_id' =>
                                $product->id,

                            'name' =>
                                $name,

                        ]),

                    'redirect_url' =>
                        '/marketplace',
                ]);
            }
 
            $product->update([

                'visibility_expiration_notified_at' =>
                    now(),

            ]);
        }

        $this->info(
            $products->count() .
            ' product expiration notification(s) created.'
        );
    }
}
