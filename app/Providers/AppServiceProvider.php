<?php

namespace App\Providers;

use View;
use Cache;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use Modules\Page\App\Models\Footer;
use Modules\Ecommerce\Entities\Cart;
use Modules\Listing\Entities\Listing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Modules\Category\Entities\Category;
use Modules\Page\App\Models\CustomPage;
use Modules\Blog\App\Models\BlogCategory;
use Modules\Currency\App\Models\Currency;
use Modules\Language\App\Models\Language;
use Modules\GlobalSetting\App\Models\GlobalSetting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        try{
            $setting = Cache::rememberForever('setting', function(){
                $setting_data = GlobalSetting::get();

                $setting = array();

                foreach($setting_data as $data_item){
                    $setting[$data_item->key] = $data_item->value;
                }

                $setting = (object) $setting;

                return $setting;
            });


            $timezone_setting = Cache::get('setting');

            config(['app.timezone' => $timezone_setting->timezone]);
            date_default_timezone_set($timezone_setting->timezone);

            View::composer('*', function($view){

                $general_setting = Cache::get('setting');
                $language_list = Language::where('status', 1)->get();
                $currency_list = Currency::where('status', 'active')->get();
                $custom_pages = CustomPage::where('status', 1)->get();
                $services = Listing::latest()->take(5)->get();

                $footer_categories = Category::where('status', 'enable')->latest()->take(7)->get();
                $footer_blog_categories = BlogCategory::where('status', 1)->latest()->take(7)->get();

                $footer = Footer::first();
                $userId = auth()->id();
                $totalCart = Cart::where('user_id', $userId)->count();

                $view->with('services', $services);
                $view->with('general_setting', $general_setting);
                $view->with('language_list', $language_list);
                $view->with('currency_list', $currency_list);
                $view->with('footer', $footer);
                $view->with('custom_pages', $custom_pages);
                $view->with('footer_categories', $footer_categories);
                $view->with('footer_blog_categories', $footer_blog_categories);

            });

        }catch(Exception $ex){
            Log::info('AppServiceProvider : '. $ex->getMessage());

            Artisan::call('optimize:clear');
        }




    }
}
