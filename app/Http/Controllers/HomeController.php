<?php

namespace App\Http\Controllers;

use App\Models\Slider;
use App\Models\Team;
use Illuminate\Support\Facades\Session;
use App\Rules\Captcha;
use Illuminate\Http\Request;
use Modules\Blog\App\Models\BlogCategory;
use Modules\FAQ\App\Models\Faq;
use Modules\Blog\App\Models\Blog;
use Modules\Listing\Entities\Listing;
use Modules\Page\App\Models\ContactUs;
use Modules\Category\Entities\Category;
use Modules\Page\App\Models\CustomPage;
use Modules\Blog\App\Models\BlogComment;
use Modules\Currency\App\Models\Currency;
use Modules\Language\App\Models\Language;
use Modules\Page\App\Models\PrivacyPolicy;
use Modules\Page\App\Models\TermAndCondition;
use Modules\Project\App\Models\Project;
use Modules\SeoSetting\App\Models\SeoSetting;
use Modules\Testimonial\App\Models\Testimonial;
use Modules\GlobalSetting\App\Models\GlobalSetting;

class HomeController extends Controller
{

    public function index(Request $request)
    {

        $theme_setting = GlobalSetting::where('key', 'selected_theme')->first();

        // List of all supported themes
        $supported_themes = [
            'main_demo',
            'digital_agency',
            'it_consulting',
            'it_solutions',
            'soft_company',
            'startup_home',
            'tech_agency',
            'tech_company'
        ];

        // Determine selected theme
        $selected_theme = $theme_setting && in_array($theme_setting->value, $supported_themes)
            ? $theme_setting->value
            : 'main_demo';

        if ($request->has('theme')) {
            $requested_theme = $request->input('theme');
            if (in_array($requested_theme, $supported_themes)) {
                $selected_theme = $requested_theme;
                Session::put('selected_theme', $selected_theme);
            }
        } elseif (!Session::has('selected_theme')) {
            Session::put('selected_theme', $selected_theme);
        } else {
            $selected_theme = Session::get('selected_theme');
        }

        $listings = Listing::where(['status' => 'enable'])
            ->latest()
            ->take(10)
            ->get();

        $services = Listing::latest()->take(5)->get();

        $blogPosts = Blog::latest()->take(4)->get();

        $testimonials = Testimonial::where('status', 'active')->latest()->get();

        $projects = Project::latest()->take(6)->get();

        $seo_setting = SeoSetting::find(1);

        $categories = Category::where('status', 'enable')->latest()->take(4)->get();
        $filter_array = $categories->pluck('id')->toArray();

        $home2_filter_service = Listing::where(['status' => 'enable'])
            ->whereIn('category_id', $filter_array)
            ->latest()
            ->take(8)
            ->get();

        $teams = Team::latest()->get();

        $faqs = Faq::latest()->take(4)->get();

        $sliders = Slider::latest()->get();

        $contact_us = ContactUs::first();

        // Common data for all views
        $view_data = compact(
            'seo_setting',
            'categories',
            'listings',
            'blogPosts',
            'testimonials',
            'home2_filter_service',
            'testimonials',
            'projects',
            'teams',
            'faqs',
            'sliders',
            'services',
            'contact_us',
        );

        // View template mapping
        $theme_view_mapping = [
            'main_demo' => 'frontend.templates.main_demo',
            'digital_agency' => 'frontend.templates.digital_agency',
            'it_consulting' => 'frontend.templates.it_consulting',
            'it_solutions' => 'frontend.templates.it_solutions',
            'soft_company' => 'frontend.templates.soft_company',
            'startup_home' => 'frontend.templates.startup_home',
            'tech_agency' => 'frontend.templates.tech_agency',
            'tech_company' => 'frontend.templates.tech_company',
        ];

        // Default to main_demo if theme is not found
        $view_template = $theme_view_mapping[$selected_theme] ?? 'frontend.templates.main_demo';

        return view($view_template, $view_data);
    }

    public function about_us()
    {
        $pageTitle = trans('translate.About Us');

        $teams = Team::latest()->take(4)->get();

        $seo_setting = SeoSetting::where('id', 3)->first();

        return view('about_us', [
            'seo_setting' => $seo_setting,
            'teams' => $teams,
            'pageTitle' => $pageTitle
        ]);
    }

    public function blogs(Request $request)
    {
        $blogs = Blog::with('author', 'category')->where('status', 1);

        // Search by title
        if ($request->search) {
            $blogs = $blogs->whereHas('translate', function($query) use ($request) {
                $query->where('title', 'LIKE', '%' . $request->search . '%');
            });
        }

        // Filter by category
        if($request->category){
            $blogs = $blogs->whereHas('category', function($query) use ($request) {
                $query->where('slug', $request->category);
            });
        }

        // Filter by tag
        if ($request->tag) {
            $blogs = $blogs->where(function($query) use ($request) {
                $query->whereJsonContains('tags', ['value' => $request->tag])
                    ->orWhereJsonContains('tags', $request->tag);
            });
        }

        // Get categories with active blog count
        $categories = BlogCategory::withCount(['blogs' => function($query) {
            $query->where('status', 1);
        }])->latest()->take(6)->get();

        $perPage = $request->type === 'grid' ? 9 : 4;
        $blogs = $blogs->paginate($perPage);

        $currentBlogId = $request->id ?? null;
        $recent_blogs = Blog::where('status', 1)
            ->when($currentBlogId, function($query) use ($currentBlogId) {
                return $query->where('id', '!=', $currentBlogId);
            })
            ->latest()
            ->take(4)
            ->get();
        $seo_setting = SeoSetting::where('id', 2)->first();

        // Get all blog tags
        $allTags = Blog::where('status', 1)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->map(function($tags) {
                return collect(json_decode($tags))
                    ->pluck('value');
            })
            ->flatten()
            ->unique()
            ->values();

        return view('blogs', [
            'blogs' => $blogs,
            'seo_setting' => $seo_setting,
            'categories' => $categories,
            'recent_blogs' => $recent_blogs,
            'allTags' => $allTags,
        ]);
    }

    public function blog($slug)
    {
        $blog = Blog::with('author')->where('status', 1)->where('slug', $slug)->firstOrFail();

        $blog_comments = BlogComment::where('blog_id', $blog->id)->where('status', 1)->latest()->get();
        $categories = BlogCategory::withCount('blogs')->take(6)->get();

        $currentBlogId = $request->id ?? null;
        $recent_blogs = Blog::where('status', 1)
            ->when($currentBlogId, function($query) use ($currentBlogId) {
                return $query->where('id', '!=', $currentBlogId);
            })
            ->latest()
            ->take(4)
            ->get();

        $previous = Blog::where('id', '<', $blog->id)
            ->where('status', 1)
            ->latest()
            ->first();

        $next = Blog::where('id', '>', $blog->id)
            ->where('status', 1)
            ->where('blog_category_id', $blog->blog_category_id) // Same category only
            ->first();


        // Get all blog tags
        $allTags = Blog::where('status', 1)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->map(function($tags) {
                return collect(json_decode($tags))
                    ->pluck('value');
            })
            ->flatten()
            ->unique()
            ->values();

        return view('blog_detail', [
            'blog' => $blog,
            'blog_comments' => $blog_comments,
            'categories' => $categories,
            'recent_blogs' => $recent_blogs,
            'previous' => $previous,
            'next' => $next,
            'allTags' => $allTags,
        ]);
    }

    public function pricing()
    {
        $pageTitle = 'Pricing Plan';
        $faqs = Faq::latest()->take(5)->get();

        return view('frontend.pricing', compact('pageTitle', 'faqs'));
    }

    public function store_blog_comment(Request $request, $id){
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'comment' => 'required',
            'g-recaptcha-response'=>new Captcha()
        ], [
            'name.required' => trans('translate.Name is required'),
            'email.required' => trans('translate.Email is required'),
            'comment.required' => trans('translate.Comment is required'),
        ]);

        $blog_comment = new Blogcomment();
        $blog_comment->blog_id = $id;
        $blog_comment->name = $request->name;
        $blog_comment->email = $request->email;
        $blog_comment->comment = $request->comment;
        $blog_comment->status = 0;
        $blog_comment->save();

        $notify_message= trans('translate.Comment submitted successfully');
        $notify_message=array('message'=>$notify_message,'alert-type'=>'success');
        return redirect()->back()->with($notify_message);
    }

    public function contact_us()
    {
        $contact_us = ContactUs::first();

        $seo_setting = SeoSetting::where('id', 4)->first();

        return view('contact_us', [
        'contact_us' => $contact_us,
        'seo_setting' => $seo_setting,
        ]);
    }

    public function faq()
    {
        $faqs = Faq::latest()->get();
        $pageTitle = 'FAQs';

        $seo_setting = SeoSetting::where('id', 5)->first();

        return view('faq', compact('faqs', 'pageTitle', 'seo_setting'));
    }

    public function teams()
    {
        $teams = Team::latest()->get();
        $pageTitle = 'Our Teams';

        $seo_setting = SeoSetting::where('id', 11)->first();

        return view('frontend.teams', [
            'teams' => $teams,
            'seo_setting' => $seo_setting,
            'pageTitle' => $pageTitle
        ]);
    }

    public function teamPerson($slug)
    {

        $team = Team::with('translate')->where('slug', $slug)->firstOrFail();
        $pageTitle = $team->translate->name;

        return view('frontend.team_single', compact('team', 'pageTitle'));
    }

    public function testimonials()
    {
        $pageTitle = 'Testimonials';
        $testimonials = Testimonial::with('translate')->active()->latest()->get();

        return view('frontend.testimonials', compact('testimonials', 'pageTitle'));
    }



    public function privacy_policy()
    {
        $privacy_policy = PrivacyPolicy::first();

        $seo_setting = SeoSetting::where('id', 9)->first();

        return view('privacy_policy', ['privacy_policy' => $privacy_policy, 'seo_setting' => $seo_setting]);
    }

    public function terms_conditions()
    {
        $terms_conditions = TermAndCondition::first();

        $seo_setting = SeoSetting::where('id', 6)->first();

        return view('terms_conditions', ['terms_conditions' => $terms_conditions, 'seo_setting' => $seo_setting]);
    }

    public function custom_page($slug)
    {
        $custom_page = CustomPage::where('slug', $slug)->firstOrFail();

        return view('custom_page', ['custom_page' => $custom_page]);
    }

    public function services(Request $request)
    {
        $services = Listing::where(['status' => 'enable'])->latest()->get();

        $seo_setting = SeoSetting::where('id', 10)->first();

        $categories = Category::where('status', 'enable')->latest()->get();

        return view('services', [
            'services_list' => $services,
            'seo_setting' => $seo_setting,
            'categories' => $categories,
        ]);
    }

    public function service(Request $request, $slug)
    {
        $service = Listing::where(['status' => 'enable', 'slug' => $slug])->firstOrFail();

        $showServices = Listing::where('id', '!=', $service->id)->where('status', 'enable')->latest()->take(5)->get();

        return view('service_detail', [
            'service' => $service,
            'showServices' => $showServices
        ]);
    }

    public function language_switcher(Request $request)
    {
        $request_lang = Language::where('lang_code', $request->lang_code)->first();
        if ($request_lang) {
            Session::put('front_lang', $request->lang_code);
            Session::put('front_lang_name', $request_lang->lang_name);
            Session::put('lang_dir', $request_lang->lang_direction);

            app()->setLocale($request->lang_code);

            $notify_message = trans('translate.Language switched successfully');
            if (env('APP_MODE') == 'DEMO') {
                $notify_message = array('message' => $notify_message, 'alert-type' => 'success', 'demo_mode' => 'Demo mode does not translate all languages');
            } else {
                $notify_message = array('message' => $notify_message, 'alert-type' => 'success');
            }

            return redirect()->back()->with($notify_message);
        } else {
            return redirect()->back()->with(['message' => trans('translate.Language not found'), 'alert-type' => 'error']);
        }
    }

    public function currency_switcher(Request $request){

        $request_currency = Currency::where('currency_code', $request->currency_code)->first();

        Session::put('currency_name', $request_currency->currency_name);
        Session::put('currency_code', $request_currency->currency_code);
        Session::put('currency_icon', $request_currency->currency_icon);
        Session::put('currency_rate', $request_currency->currency_rate);
        Session::put('currency_position', $request_currency->currency_position);

        $notify_message= trans('translate.Currency switched successful');
        $notify_message=array('message'=>$notify_message,'alert-type'=>'success');
        return redirect()->back()->with($notify_message);

    }

    public function download_submission_file($file){
        $filepath= public_path() . "/uploads/custom-images/".$file;
        return response()->download($filepath);
    }

    public function portfolio(Request $request)
    {

        if($request->type == 'grid'){
            $projects = Project::latest()->paginate(9);
        }else{
            $projects = Project::latest()->paginate(10);
        }



        return view('frontend.templates.portfolio', compact('projects'));
    }

    public function portfolioShow($slug)
    {
        $project = Project::where('slug', $slug)->firstOrFail();
        $previousProject = Project::where('id', '<', $project->id)->orderBy('id', 'desc')->first();
        $nextProject = Project::where('id', '>', $project->id)->orderBy('id', 'asc')->first();

        return view('frontend.templates.portfolio_detail', ['project' => $project, 'previousProject' => $previousProject, 'nextProject' => $nextProject]);
    }

}
