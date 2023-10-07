<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use DB;

class AuthController extends Controller
{

    protected $account;

    public function __construct(Account $_account) {
        $this->account = $_account;
    }
    public function register(Request $request){
        $validator = Validator::make($request->all(),
        [
         'username'=>'required',
         'password'=>'required',
         'email'=>'required',
        ]
        );

        return Account::create([
            'username' => $request->input('username'),
            'password' => Hash::make($request->input('password')),
        ]);
    }

    public function login(Request $request)
    {
        try {
             // Kiểm tra xem người dùng đã đăng nhập hay chưa (nó check token trong bảng personal_access_token)
            if (auth('sanctum')->check()) {
                return response()->error("Bạn đã đăng nhập và không thể truy cập API đăng nhập lại.", 403);
            }

            // Xác thực người dùng và lấy thông tin người dùng
            if (Auth::attempt($request->only('email', 'password'))) {
                $user = Auth::user();
                // Tạo token Sanctum cho người dùng
                
                $account = DB::select(' SELECT username, email, avatar, phone, location
                            FROM db_lab.Account
                            WHERE email = ? ',[$request->input('email')]);
                
                $token = $user->createToken('token-name')->plainTextToken;
    
                $result = [
                    'data'=>$account[0],
                    'authentication' => [
                        'access_token' => $token,
                        'token_type' => 'Bearer',
                    ]
                ];
                return response()->success($result,"Đăng nhập thành công !",200);
            }
            // Xác thực thất bại
            return response()->error('Sai tài khoản này không tồn tại !', 200);
        } catch (Exception $th) {
            throw $th;
        }
    }

    public function logout(Request $request)
    {
        Auth::user()->tokens->each(function ($token, $key) {
            $token->delete();
        });

        return response()->success([],"Đăng xuất thành công !",200);;
    }

    public function uploadFile(Request $request){
        try {
           // up ảnh
           $imageInfo = array();
           if ($request->hasFile('media')) {
               $images = $request->file('media');
               foreach ($images as $image) {
                   $originalName = $image->getClientOriginalName();
                   $extension = $image->getClientOriginalExtension();
                   $randomString = uniqid();
                   $imageName = time() . '_' . $originalName . '_' . $randomString . '.' . $extension;
                   $image->move(public_path('storage/media'), $imageName);
                   $imageInfo[] = ['type' => $image->getClientOriginalExtension(),'name' => $imageName];
               }
               return response()->success(['file_info' => $imageInfo],'Tải lên ảnh thành công',200);
           }
           return response()->error('Tải lên ảnh thất bại !',400);
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function show($image)
    {
        $path = storage_path('app/public/media/' . $image);
        
        if (!File::exists($path)) {
            abort(404);
        }
        
        $file = File::get($path);
        $type = File::mimeType($path);

        $response = response($file, 200)->header("Content-Type", $type);

        return $response;
    }
}
