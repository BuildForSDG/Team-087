<?php

use App\User;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

/**
 * @covers App\Http\AuthController
 * @uses App\User
 */
class AuthControllerTest extends TestCase
{
    use DatabaseMigrations;

    private $apiV1RegisterUrl;
    private $apiV1VerifyUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed --class=UsersTableSeeder');
        $this->withoutEvents();

        $this->apiV1RegisterUrl = $this->apiV1 . '/auth/register';
        $this->apiV1VerifyUrl = $this->apiV1 . '/auth/verify';
    }

    /**
     * Should Pass If Fresh Email is Provided
     *
     * @return void
     */
    public function testUserCanBeCreatedWithFreshAndUnusedEmail()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = array_merge(factory(User::class)->make()->toArray(), ['password' => 'passw0rd', 'password_confirmation' => 'passw0rd']);

        $this->json('POST', $this->apiV1RegisterUrl, $user)->seeStatusCode(201)->seeInDatabase('users', ['email' => $user['email']]);
        $this->seeJson(['status' => true])->seeJsonStructure(['data' => ['id', 'email', 'created_at'], 'message'])->seeJsonDoesntContains(['errors']);
    }

    /**
     * Should Fail If Existing Email is Provided
     *
     * @return void
     */
    public function testUserCannotBeCreatedWithExistingEmail()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = array_merge(User::first()->toArray(), ['password' => 'passw0rd', 'password_confirmation' => 'passw0rd']);

        $this->json('POST', $this->apiV1RegisterUrl, $user)->assertResponseStatus(400);
        $this->seeJson(['status' => false])->seeJsonStructure(['errors' => ['email'], 'message'])->seeJsonDoesntContains(['data']);
    }

    /**
     * Should fail if invalid parameters are submitted
     *
     * @return void
     */
    public function testUserCannotBeCreatedWithInvalidParameters()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = ['last_name' => 'Meyer', 'first_name' => 'Jack', 'email' => 'mentalapp@', 'gender' => 'males'];
        $this->post($this->apiV1RegisterUrl, $user)->assertResponseStatus(400);
        $this->seeJson(['status' => false])->seeJsonStructure(['errors', 'message'])->seeJsonDoesntContains(['data']);
    }

    /**
     * Should fail verification for incorrect verification-code
     *
     * @return void
     */
    public function testUserCannotBeVerifiedWithIncorrectCode()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = User::where(['is_active' => false])->first();
        $this->get("{$this->apiV1VerifyUrl}?code=x-{$user->profile_code}&email={$user->email}")->assertResponseStatus(400);
        $this->seeJson(['status' => false])->seeJsonStructure(['errors', 'message'])->seeJsonDoesntContains(['data']);
    }

    /**
     * Should fail verification for incorrect email-address
     *
     * @return void
     */
    public function testUserCannotBeVerifiedWithIncorrectEmail()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = User::where(['is_active' => false])->first();
        $this->get("{$this->apiV1VerifyUrl}?code={$user->profile_code}&email=x-{$user->email}")->assertResponseStatus(404);
        $this->seeJson(['status' => false])->seeJsonStructure(['errors', 'message'])->seeJsonDoesntContains(['data']);
    }

    /**
     * Should pass verification for correct verification-code and email-address
     *
     * @return void
     */
    public function testUserCanBeVerifiedWithCorrectCodeAndEmail()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = User::where(['is_active' => false])->first();
        $this->get("{$this->apiV1VerifyUrl}?code={$user->profile_code}&email={$user->email}")->assertResponseStatus(200);
        $this->seeJson(['status' => true])->seeJsonStructure(['data', 'message'])->seeJsonDoesntContains(['errors']);
    }

    /**
     * Should fail verification for already verified account
     *
     * @return void
     */
    public function testUserCannotBeVerifiedTwiceForAlreadyVerifiedAccount()
    {
        $this->get('/')->assertResponseStatus(200);

        $user = factory(User::class)->create(['is_active' => true, 'is_guest' => false]);
        $this->get("{$this->apiV1VerifyUrl}?code={$user->profile_code}&email={$user->email}")->assertResponseStatus(400);
        $this->seeJson(['status' => false])->seeJsonStructure(['errors', 'message'])->seeJsonDoesntContains(['data']);
    }
}
