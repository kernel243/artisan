<?php

use App\Data\Invoices\InvoiceData;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Config;

describe('kernel:action', function () {
    it('creates a create action around a DTO in a transaction', function () {
        $this->makeModel('Post');

        $this->artisan('kernel:action', ['name' => 'CreatePost', '--model' => 'Post'])->assertSuccessful();

        $this->assertValidPhp('app/Actions/Posts/CreatePostAction.php');
        expect(file_get_contents($this->sandboxPath('app/Actions/Posts/CreatePostAction.php')))
            ->toContain('namespace App\Actions\Posts;')
            ->toContain('use App\Data\Posts\PostData;')
            ->toContain('public function handle(PostData $data): Post')
            ->toContain('DB::transaction(fn (): Post => Post::query()->create($data->toArray()))');
    });

    it('uses the partial update DTO when it exists', function () {
        $this->makeModel('Post');
        $this->writeFixture('app/Data/Posts/PostUpdateData.php', '<?php');

        $this->artisan('kernel:action', ['name' => 'UpdatePost', '--model' => 'Post'])->assertSuccessful();

        $this->assertValidPhp('app/Actions/Posts/UpdatePostAction.php');
        expect(file_get_contents($this->sandboxPath('app/Actions/Posts/UpdatePostAction.php')))
            ->toContain('public function handle(Post $post, PostUpdateData $data): Post')
            ->toContain('use ($post, $data): Post');
    });

    it('creates a delete action', function () {
        $this->makeModel('Post');

        $this->artisan('kernel:action', ['name' => 'Posts/DeletePost', '--model' => 'Post'])->assertSuccessful();

        $this->assertValidPhp('app/Actions/Posts/DeletePostAction.php');
        expect(file_get_contents($this->sandboxPath('app/Actions/Posts/DeletePostAction.php')))
            ->toContain('public function handle(Post $post): void')
            ->not->toContain('DB::');
    });

    it('follows the project conventions from the config', function () {
        Config::set('kernel-artisan.actions', ['suffix' => '', 'method' => '__invoke', 'transaction' => false]);
        $this->makeModel('Receipt');

        $this->artisan('kernel:action', ['name' => 'CreateReceipt', '--model' => 'Receipt', '--no-data' => true])->assertSuccessful();

        $this->assertValidPhp('app/Actions/Receipts/CreateReceipt.php');
        expect(file_get_contents($this->sandboxPath('app/Actions/Receipts/CreateReceipt.php')))
            ->toContain('class CreateReceipt')
            ->toContain('public function __invoke(array $attributes): Receipt')
            ->toContain('return Receipt::query()->create($attributes);');
    });

    it('creates a plain action without model', function () {
        $this->artisan('kernel:action', ['name' => 'Billing/SendMonthlyInvoices'])->assertSuccessful();

        $this->assertValidPhp('app/Actions/Billing/SendMonthlyInvoicesAction.php');
    });

    it('requires a model for crud actions', function () {
        $this->artisan('kernel:action', ['name' => 'CreatePost'])->assertFailed();
    });
});

describe('kernel:data', function () {
    it('creates a DTO from fields', function () {
        $this->artisan('kernel:data', ['name' => 'Posts/PostData', '--fields' => 'title:string,price:decimal,published_at:datetime?,author_id:foreignId'])
            ->assertSuccessful();

        $this->assertValidPhp('app/Data/Posts/PostData.php');
        expect(file_get_contents($this->sandboxPath('app/Data/Posts/PostData.php')))
            ->toContain('public string $title,')
            ->toContain('public float $price,')
            ->toContain('public CarbonInterface|null $published_at,')
            ->toContain('public int $author_id,');
    });

    it('reads the fields of an existing model and builds a working DTO', function () {
        $this->writeFixture('app/Models/Invoice.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['number', 'amount', 'is_paid', 'customer_id'];

    // $casts rather than casts(): the method does not exist before Laravel 11.
    protected $casts = ['amount' => 'decimal:2', 'is_paid' => 'boolean'];
}
PHP);
        require_once $this->sandboxPath('app/Models/Invoice.php');

        $this->artisan('kernel:data', ['name' => 'InvoiceData', '--model' => 'Invoice'])->assertSuccessful();

        $path = $this->sandboxPath('app/Data/Invoices/InvoiceData.php');
        $this->assertValidPhp('app/Data/Invoices/InvoiceData.php');
        require_once $path;

        $data = InvoiceData::from(['number' => 'F-001', 'amount' => '12.50', 'is_paid' => true, 'customer_id' => 3]);

        expect($data->toArray())->toBe(['number' => 'F-001', 'amount' => 12.5, 'is_paid' => true, 'customer_id' => 3]);
    });

    it('creates a partial update DTO', function () {
        $this->artisan('kernel:data', ['name' => 'Posts/PostUpdateData', '--fields' => 'title:string', '--update' => true])->assertSuccessful();

        $this->assertValidPhp('app/Data/Posts/PostUpdateData.php');
        expect(file_get_contents($this->sandboxPath('app/Data/Posts/PostUpdateData.php')))
            ->toContain('use Spatie\LaravelData\Optional;')
            ->toContain('public Optional|string $title,');
    });

    it('rejects unknown field types', function () {
        $this->artisan('kernel:data', ['name' => 'PostData', '--fields' => 'title:strnig'])
            ->expectsOutputToContain('Unknown type')
            ->assertFailed();
    });
});

describe('kernel:request', function () {
    it('creates the store and update requests', function () {
        $this->artisan('kernel:request', ['model' => 'Post', '--fields' => 'title:string,email:email:unique,bio:text?,author_id:foreignId'])
            ->assertSuccessful();

        $this->assertValidPhp('app/Http/Requests/Posts/StorePostRequest.php');
        $this->assertValidPhp('app/Http/Requests/Posts/UpdatePostRequest.php');

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/Posts/StorePostRequest.php')))
            ->toContain("'email' => ['required', 'string', 'email', 'max:255', Rule::unique('posts', 'email')],")
            ->toContain("'bio' => ['nullable', 'string'],")
            ->toContain("Rule::exists('authors', 'id')")
            ->toContain("'author_id' => 'author',")
            ->toContain('Indiquez :attribute.');

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/Posts/UpdatePostRequest.php')))
            ->toContain("->ignore(\$this->route('post'))");
    });

    it('can authorize with a permission instead of a policy', function () {
        Config::set('kernel-artisan.requests.authorize_in_controller', false);

        $this->artisan('kernel:request', ['model' => 'BlogPost', '--fields' => 'title:string', '--only' => 'store'])->assertSuccessful();

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/BlogPosts/StoreBlogPostRequest.php')))
            ->toContain("\$this->user()?->can('blog-posts.create') ?? false")
            ->not->toContain('use Illuminate\Validation\Rule;');
        expect($this->sandboxPath('app/Http/Requests/BlogPosts/UpdateBlogPostRequest.php'))->not->toBeFile();
    });
});

describe('kernel:enum', function () {
    it('creates a backed enum with labels and options', function () {
        $this->artisan('kernel:enum', ['name' => 'PaymentStatus', '--cases' => 'pending,paid,partially_refunded'])->assertSuccessful();

        $this->assertValidPhp('app/Enums/PaymentStatus.php');
        $this->assertValidPhp('app/Enums/Concerns/HasOptions.php');

        require_once $this->sandboxPath('app/Enums/Concerns/HasOptions.php');
        require_once $this->sandboxPath('app/Enums/PaymentStatus.php');

        expect(PaymentStatus::values())->toBe(['pending', 'paid', 'partially_refunded'])
            ->and(PaymentStatus::PartiallyRefunded->label())->toBe('Partially refunded')
            ->and(PaymentStatus::options()[0])->toBe(['value' => 'pending', 'label' => 'Pending']);
    });

    it('creates a valid enum without cases', function () {
        $this->artisan('kernel:enum', ['name' => 'Status'])->assertSuccessful();

        $this->assertValidPhp('app/Enums/Status.php');
    });
});

it('publishes the config', function () {
    $this->artisan('vendor:publish', ['--tag' => 'kernel-config'])->assertSuccessful();

    expect($this->sandboxPath('config/kernel-artisan.php'))->toBeFile();
});
