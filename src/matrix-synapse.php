<?php declare(strict_types=1);
namespace MatrixSynapse;

class DatabaseConnection {
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
        $entry = ( count($results) > 0 ) ? $results[0] : null; 
        return ( $entry ) ? $entry[$field] : null;
    }
}

class Homeserver extends DatabaseConnection {
    public function __construct() {
        parent::__construct("sqlite:/var/lib/matrix-synapse/homeserver.db");
    }

    public function verifyAccount( string $username, string $password ): bool {
	$user_id = sprintf( "@%s:homeservergoeshere.url", $username );
        $password_hash = $this->queryField(
            "password_hash",
            "SELECT password_hash FROM users WHERE name=?", 
            array( $user_id ) 
        );
        return ( $password_hash && password_verify($password, $password_hash) );
    }

    public function verifyGroup( string $username, string $group ): bool {
	    $user_id = sprintf( "@%s:homeservergoeshere.url", $username );
        $group_id = sprintf( "+%s:homeservergoeshere.url", $group );
        $is_admin = $this->queryField(
            "is_admin",
            "SELECT is_admin FROM group_users WHERE user_id=? AND group_id=?",
            array( $user_id, $group_id )
        );

        return ( $is_admin && $is_admin == 1 );
    }

    public function makeAdmin( string $username, string $group ): void {	
        $user_id = sprintf( "@%s:homeservergoeshere.url", $username );
        $group_id = sprintf( "+%s:homeservergoeshere.url", $group );
        $this->query(
            "UPDATE group_users SET is_admin=1 WHERE user_id=? AND group_id=?",
            array( $user_id, $group_id )
        );
        $this->query(
            "UPDATE local_group_membership SET is_admin=1 WHERE user_id=? AND group_id=?",
            array( $user_id, $group_id )
        );
    }

    public function registerUser( string $username, string $password_hash, string $group_id ): bool {   
        $user_id = sprintf("@%s:homeservergoeshere.url", $username);
        if ( !empty(
            $this->queryField(
                "name",
                "SELECT name FROM users WHERE name=?",
                array( $user_id )
            )
        ) ) {
		    trigger_error("Could not create account. Username may be taken.", E_USER_WARNING);
		    return False;
	    }
         
	    $timestamp = time();
        $this->query(
                "INSERT INTO users (name, password_hash, creation_ts) VALUES (?, ?, ?)",
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
                "INSERT INTO group_users VALUES (?, ?, 0, 1)",
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
	    catch (PDOException $e) {
		    trigger_error("Could not add account to group. Account still made.", E_USER_WARNING);
	    }
        return True;
    }    
}

class Invites extends DatabaseConnection {
    private $expirationTime; //seconds, default is 2 weeks

    public function __construct( $expirationTime = 604800 * 2 ) {
        parent::__construct("sqlite:/etc/matrix-synapse-registration/invites.db");
        $this->expirationTime = $expirationTime;
    }

    public function verifyInvite( string $invite, int $cutoff = null ): bool {
        if ( $cutoff == null ) { $cutoff = $this->expirationTime; }
        $results = $this->query(
            "SELECT * FROM invites WHERE code=?",
            array( $invite ) 
        );

        $entry = ( count($results) > 0 ) ? $results[0] : null; 
        $creation_ts = ( $entry ) ? $entry['creation_ts'] : null;
        $expiration_ts = ( $entry ) ? $entry['expiration_ts'] : null;

        if ( !($creation_ts) || !($expiration_ts) ) { return false; }
        if ( $expiration_ts < time() || $creation_ts < $cutoff ) { return false; }
        return true;
    }

    public function fetchGroup( string $invite ): ?string {
        $group = $this->queryField(
            "group_id",
            "SELECT * FROM invites WHERE code=?",
            array( $invite )
        );
        return $group;
    }

    public function removeInvite( string $invite ): bool {
        $removed = $this->query(
            "DELETE FROM invites WHERE code=?",
            array( $invite )
        );
        return boolval( $removed );
    }

    public function generateInvite( string $group ): string {
        $group_id = sprintf("+%s:homeservergoeshere.url", $group);
        $timestamp = time();
        $expiration = $timestamp + $this->expirationTime;
        $invite = base64_encode(random_bytes(6));
	while ( !(preg_match('/^[a-zA-Z0-9]+$/', $invite)) ) {
		$invite = base64_encode(random_bytes(6));
	}
        $this->query(
            "INSERT INTO invites (code, creation_ts, expiration_ts, group_id) VALUES (?, ?, ?, ?)",
            array( $invite, $timestamp, $expiration, $group_id )
        );

        return $invite;
    }
}

function promoteUser( string $username, string $password, string $group, string $admin ): bool {
    $homeserver = new Homeserver();
    if ( !$homeserver->verifyAccount($username, $password) ) {
        trigger_error( "Could not verify account. Your username or password may be incorrect.", E_USER_WARNING ); 
        return False;

	}
	if ( !$homeserver->verifyGroup($username, $group) ) {
        trigger_error( "Could not find group or you are not an admin of the group.", E_USER_WARNING ); 
        return False;
    }

    $homeserver->makeAdmin( $admin, $group );
    return True;
}

function generateInvite( string $username, string $password, string $group ): ?string {
    $homeserver = new Homeserver();
    if ( !$homeserver->verifyAccount($username, $password) ) {
        trigger_error( "Could not verify account. Your username or password may be incorrect.", E_USER_WARNING ); 
        return null;
	}
	if ( !$homeserver->verifyGroup($username, $group) ) {
        trigger_error( "Could not find group or you are not an admin of the group.", E_USER_WARNING ); 
        return null;
    }

    $invites = new Invites();
    $invite = $invites->generateInvite( $group );
	return $invite;
}

function registerUser( $username, $password_hash, $invite ): bool {
    if ( !preg_match('/^[a-zA-Z][a-zA-Z0-9]{1,16}$/', $username) ) { 
        trigger_error("Invalid username", E_USER_ERROR); 
        return False; 
    }
    if ( empty($password_hash) ) { 
        trigger_error("Please enter a password", E_USER_ERROR); 
        return False; 
    }

    $invites = new Invites();
	if ( !$invites->verifyInvite( $invite) ) {
		trigger_error("Invalid invite!", E_USER_WARNING);
		return False;
	}
	
	$group_id = $invites->fetchGroup( $invite );
    if ( $group_id == null ) { 
        trigger_error("Could not find group. Try re-generating the invite."); 
        return False; 
    }

	
    $homeserver = new Homeserver();

    $accountMade = $homeserver->registerUser( $username, $password_hash, $group_id );
    if ( $accountMade ) {
	    $invites->removeInvite( $invite );
    }
	return $accountMade;
}
