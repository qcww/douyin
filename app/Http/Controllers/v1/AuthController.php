<?php  

/**
 * 用户验证类
 *
 * JWT - Json Web Token [https://jwt.io/]
 * php-jwt : https://github.com/lcobucci/jwt
 * Document: https://github.com/lcobucci/jwt/blob/3.2/README.md
 */

namespace App\Http\Controllers\v1;

use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;

class AuthController extends Controller {

    private $config = [
        'iss' => '',
        'aud' => '',
        'id'  => '',
        'iat' => '',
        'nbf' => '',
        'exp' => '',
    ];

    public $expTime = 7200;

    public function __construct() {
        $this->config = $this->getJWTConfig();
    }

    /**
     * JWT token 配置
     * 
     * @param void
     * @return array
     */
    public function getJWTConfig() {
        $currentTime = time();
        return [
            'iss' => env('JWT_iss'), // issuer
            'aud' => env('JWT_aud'),  // audience
            'id'  => env('JWT_id'),
            'iat' => $currentTime, // issud at
            'nbf' => $currentTime, // not before
            'exp' => $currentTime + $this->expTime, // expires in one week
        ];
    }

    /**
     * 生成token
     *
     * @param array $payload
     * @return string
     */
    public function getToken(Array $data = []) {
        $signer = new Sha256();
        $token = (new Builder())
            ->setIssuer($this->config['iss'])
            ->setAudience($this->config['aud'])
            ->setId($this->config['id'])
            ->setIssuedAt($this->config['iat'])
            ->setNotBefore($this->config['nbf'])
            ->setExpiration($this->config['exp'])
            ->set('user', Crypt::encrypt($data)) // 加密后的用户标识
            ->sign($signer, 'secret')
            ->getToken();
        return (string) $token;
    }

    /**
     * 检验token过期
     *
     */
    public function isExpired($token) {
        $token = (new Parser())->parse((string) $token);
        return $token->isExpired();
    }

    /**
     * token中的用户信息真实性
     *
     * @param $token
     * @return bool
     */
    public function isUserReal($token) {

        $user = $this->getUserFromToken($token);

        if (is_array($user) 
            && isset($user['user_id'])
            && isset($user['i_user_type'])
            && isset($user['phone'])
        ) {
            $really_user = DB::table('user')
                ->where([
                    ['id', $user['id']],
                    ['phone', $user['phone']],
                ])
                ->first();
            if ($really_user) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * 检验token有效
     *
     */
    public function isValidated($token) {
        $token = (new Parser())->parse((string) $token);
        $claims = $token->getClaims();
        if ( $this->config['iss'] != $token->getClaim('iss', 'xh') 
            || $this->config['aud'] != $token->getClaim('aud', 'xh') 
            || $this->config['id'] != $token->getClaim('jti', 'xh') 
        ) 
        {
            return false;
        }

        return true;
    }

    /**
     * 从token中获取用户
     *
     */
    public function getUserFromToken($token) {
        $token = (new Parser())->parse((string) $token);
        $user = Crypt::decrypt($token->getClaim('user'));

        return $user;
    }

    /**
     * 验证token有效
     *
     * @param Token $token
     * @return bool|array
     */
    public function verifyToken($token = '') {
        $token = (new Parser())->parse((string) $token);

        $data = new ValidationData();
        $data->setIssuer($this->config['iss']);
        $data->setAudience($this->config['aud']);
        $data->setId($this->config['id']);
        $verified = $token->validate($data);
        if (! $verified) {
            return false;
        }

        $user = Crypt::decrypt($token->getClaim('user')); // 解密后的用户标识
        if (is_array($user) 
            && isset($user['id'])
            && isset($user['is_admin'])
            && isset($user['user_name'])
        ) {
            return $user;
        } else {
            return false;
        }
    }

    /**
     * 刷新token
     *
     * @param string $token
     * @return string $token
     */
    public function refresh($token) {

        $token = $this->getToken( $this->getUserFromToken($token) );
        if ($token) {
            return (string) $token;
        } else {
            return false;
        }
    }

    // 获取头部信息
    public function parseAuthHeader($header, $method = 'bearer')
    {
        if (! starts_with(strtolower($header), $method)) {
            return false;
        }
        return trim(str_ireplace($method, '', $header));
    }

}

?>
