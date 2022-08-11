<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPasswordRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Exception;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_if(Gate::denies('view-users'), Response::HTTP_FORBIDDEN, __('cms.authorization_error'));

        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->with('roles')
                ->where('id', '!=', Auth::id())
                ->when(\Request::input('search'), function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                    $query->orWhere('email', 'like', "%{$search}%");
                    $query->orWhereHas('roles', function ($subquery) use ($search) {
                        return $subquery->where('name', 'like',  "%{$search}%");
                    });
                })
                ->paginate(20)
                ->withQueryString()
                ->through(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_photo_url' => $user->profile_photo_url,
                    'role' => $user->roles[0]->name ?? '',
                ]),
            'filters' => \Request::only(['search'])
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_if(Gate::denies('create-users'), Response::HTTP_FORBIDDEN, __('cms.authorization_error'));

        return Inertia::render('Users/Create', [
            'roles' => Role::pluck('name')
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_if(Gate::denies('create-users'), Response::HTTP_FORBIDDEN, __('cms.authorization_error'));

        $this->validate($request, [
            'name' => 'required',
            'email' => [
                'required',
                'email',
                'unique:users,email'
            ],
            'password' => [
                'required',
                'required_with:password',
                'between:8,64',
                new StrongPasswordRule
            ],
            'password_confirmation' => [
                'required',
                'same:password'
            ],
            'role' => [
                'required'
            ]
        ]);

        try {
            // create the user
            $user = User::create([
                'name' => request('name'),
                'email' => request('email'),
                'password' => Hash::make(request('password'))
            ]);

            // assign the role to the user
            $role = Role::where('name', request('role'))->first();
            $user->assignRole($role);

            return redirect('/cms/users')->with('success', 'User created.');
        }
        catch (Exception $e) {
            return redirect('/cms/users')->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        abort_if(Gate::denies('delete-users'), Response::HTTP_FORBIDDEN, __('cms.authorization_error'));

        try {
            $user->delete();

            return redirect('/cms/users')->with('success', 'User deleted.');
        }
        catch (Exception $e) {
            return redirect('/cms/users')->with('error', $e->getMessage());
        }
    }
}