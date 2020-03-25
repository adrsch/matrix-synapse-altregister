<?php declare(strict_types=1);
namespace GRegister;

class DatabaseInterface {
    private $db;

    public function __construct( string $dbInfo ) {
        $this->db = new \PDO( $dbInfo );
        $this->db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
    }

    protected function query( string $rawQuery, array $args = null ): ?array {
        $statement = $this->db->prepare( $rawQuery );

        if ( $args ) { 
            $statement->execute( $args ); 
        } 
        else { 
            $statement->execute();
        }

        return $statement->fetchAll();
    }
    
    protected function queryField( string $field, string $rawQuery, array $args = null ) {
        $results = $this->query( $rawQuery, $args );
        $entry = ( count( $results ) > 0 ) ? $results[0] : null; 
        return ( $entry ) ? $entry[$field] : null;
    }
}

class Homeserver extends DatabaseInterface {
    private $domain;

    public function __construct( string $domain, string $dbPath ) {
        parent::__construct( sprintf( 'sqlite:%s', $dbPath ) );
    }

    public function verifyAccount( string $username, string $password ): bool {
	$user_id = sprintf( '@%s:%s', $username, $domain );
        $password_hash = $this->queryField(
            'password_hash',
            'SELECT password_hash FROM users WHERE name=?', 
            array( $user_id ) 
        );
        return ( $password_hash && password_verify( $password, $password_hash ) );
    }

    public function verifyGroup( string $username, string $group ): bool {
	    $user_id = sprintf( '@%s:%s', $username, $domain );
        $group_id = sprintf( '+%s:%s', $group, $domain );
        $is_admin = $this->queryField(
            'is_admin',
            'SELECT is_admin FROM group_users WHERE user_id=? AND group_id=?',
            array( $user_id, $group_id )
        );

        return ( $is_admin && $is_admin == 1 );
    }

    public function makeAdmin( string $username, string $group ): void {	
        $user_id = sprintf( '@%s:%s', $username, $domain );
        $group_id = sprintf( '+%s:%s', $group, $domain );
        $this->query(
            'UPDATE group_users SET is_admin=1 WHERE user_id=? AND group_id=?',
            array( $user_id, $group_id )
        );
        $this->query(
            'UPDATE local_group_membership SET is_admin=1 WHERE user_id=? AND group_id=?',
            array( $user_id, $group_id )
        );
    }

    public function registerUser( string $username, string $password_hash, string $group_id ): bool {   
        $user_id = sprintf( '@%s:%s', $username, $domain );
        if ( !empty(
            $this->queryField(
                'name',
                'SELECT name FROM users WHERE name=?',
                array( $user_id )
            )
        ) ) {
		    trigger_error( 'Could not create account. Username may be taken.', E_USER_WARNING );
		    return False;
	    }
         
	    $timestamp = time();
        $this->query(
                'INSERT INTO users (name, password_hash, creation_ts) VALUES (?, ?, ?)',
                array( $user_id, $password_hash, $timestamp )
            );
        $this->query(
            'INSERT INTO profiles VALUES (?, ?, "")',
            array( $username, $username )
        );
        $this->query(
            'INSERT INTO user_directory VALUES (?, "", ?, "")',
            array( $user_id, $username )
        );
        try {
            $this->query(
                'INSERT INTO group_users VALUES (?, ?, 0, 1)',
                array( $group_id, $user_id )
            );
            $this->query(
                'INSERT INTO local_group_membership VALUES (?, ?, 0, "join", 0, "{}")',
                array( $group_id, $user_id )
            );

            $next_id = ( int )$this->query(
                'select stream_id from local_group_updates order by stream_id desc limit 1'
            );
            $next_id++;
            $this->query(
                'INSERT INTO local_group_updates VALUES (?, ?, ?, "membership", "{""membership"": ""join"", ""content"": {}}")',
                array( strval($next_id), $group_id, $user_id )
            );
        }
	    catch ( \PDOException $e ) {
		    trigger_error( 'Could not add account to group (account still made)', E_USER_WARNING );
	    }
        return True;
    }    
}

class Invites extends DatabaseInterface {
    private $expirationTime;
    private $domain;

    public function __construct( string $domain, string $dbPath, int $expirationTime = 604800 * 2 ) {
        parent::__construct( sprintf( 'sqlite:%s', $dbPath ) );
        $this->expirationTime = $expirationTime;
        $this->domain = $domain;
    }

    public function verifyInvite( string $invite, int $cutoff = null ): bool {
        if ( $cutoff == null ) { $cutoff = $this->expirationTime; }
        $results = $this->query(
            'SELECT * FROM invites WHERE code=?',
            array( $invite ) 
        );

        $entry = ( count( $results ) > 0 ) ? $results[0] : null; 
        $creation_ts = ( $entry ) ? $entry['creation_ts'] : null;
        $expiration_ts = ( $entry ) ? $entry['expiration_ts'] : null;

        if ( !( $creation_ts ) || !( $expiration_ts ) ) { return false; }
        if ( $expiration_ts < time() || $creation_ts < $cutoff ) { return false; }
        return true;
    }

    public function fetchGroup( string $invite ): ?string {
        $group = $this->queryField(
            'group_id',
            'SELECT * FROM invites WHERE code=?',
            array( $invite )
        );
        return $group;
    }

    public function removeInvite( string $invite ): bool {
        $removed = $this->query(
            'DELETE FROM invites WHERE code=?',
            array( $invite )
        );
        return boolval( $removed );
    }

    public function generateInvite( string $group ): string {
        $group_id = sprintf( '+%s:%s', $group, $domain );
        $timestamp = time();
        $expiration = $timestamp + $this->expirationTime;
        $invite = base64_encode(random_bytes(6));
	    while ( !( preg_match( '/^[a-zA-Z0-9]+$/', $invite ) ) ) {
		    $invite = base64_encode(random_bytes(6));
        }
        $this->query(
            'INSERT INTO invites (code, creation_ts, expiration_ts, group_id) VALUES (?, ?, ?, ?)',
            array( $invite, $timestamp, $expiration, $group_id )
        );
        return $invite;
    }
}

class GRegistrar {
    private $homeserver;
    private $invites;

    public function __construct( string $configPath = 'gregister.json' ) {
        $configFile = fopen( $configPath, 'r' );
        if ( !$configFile ) { trigger_error( 'Could not find config file', E_USER_ERROR ); }
        $config = json_decode( fread( $configFile, filesize( $configPath ) ) );
        fclose( $configFile );
        if ( !isset( $config ) ) { trigger_error( 'Could not read config json', E_USER_ERROR ); }
        try {
            $this->homeserver = new Homeserver( $config->homeserverDomain, $config->homeserverDatabase );
            $this->invites = new Invites( $config->homeserverDomain, $config->invitesDatabase, $config->expirationTimeSeconds );
        }
        catch ( \PDOException $e ) {
            trigger_error( 'Could not establish connection to databases, please check the paths in your config', E_USER_ERROR );
        }
    }

    public function promoteUser( string $username, string $password, string $group, string $admin ): bool {
        if ( !$this->homeserver->verifyAccount( $username, $password ) ) {
            trigger_error( 'Could not verify account. Your username or password may be incorrect.', E_USER_WARNING ); 
            return False;

        }
        if ( !$this->homeserver->verifyGroup( $username, $group ) ) {
            trigger_error( 'Could not find group or you are not an admin of the group.', E_USER_WARNING ); 
            return False;
        }
        $this->homeserver->makeAdmin( $admin, $group );
        return True;
    }

    function generateInvite( string $username, string $password, string $group ): ?string {
        if ( !$this->homeserver->verifyAccount( $username, $password ) ) {
            trigger_error( 'Could not verify account. Your username or password may be incorrect.', E_USER_WARNING ); 
            return null;
        }
        if ( !$this->homeserver->verifyGroup( $username, $group ) ) {
            trigger_error( 'Could not find group or you are not an admin of the group.', E_USER_WARNING ); 
            return null;
        }
        $invite = $this->invites->generateInvite( $group );
        return $invite;
    }

    function registerUser( $username, $password_hash, $invite ): bool {
        if ( !preg_match( '/^[a-zA-Z][a-zA-Z0-9]{1,16}$/', $username ) ) { 
            if ( strlen( $username ) > 16 ) {
                trigger_error( 'Your username is too long!', E_USER_ERROR ); 
            }
            else {
                trigger_error( 'Invalid username', E_USER_ERROR ); 
            }
            return False; 
        }
        if ( empty( $password_hash ) ) { 
            trigger_error( 'Please enter a password.', E_USER_ERROR ); 
            return False; 
        }
        if ( !$this->invites->verifyInvite( $invite ) ) {
            trigger_error( "Invalid invite!", E_USER_WARNING );
            return False;
        }
        $group_id = $this->invites->fetchGroup( $invite );
        if ( $group_id == null ) { 
            trigger_error( 'Could not find group. Try re-generating the invite.' ); 
            return False; 
        }
        $accountMade = $this->homeserver->registerUser( $username, $password_hash, $group_id );
        if ( $accountMade ) {
            $this->invites->removeInvite( $invite );
        }
        return $accountMade;
    }
}
