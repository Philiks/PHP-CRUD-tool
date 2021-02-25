<?php
function get_model_array() {
    return array(
        0 => array(
            'class' => 'Product',
            'table_name' => 'products',
            'fields' => 'uuid,name,image_path,prices,sizes,customized,created,modified'
        ),
        1 => array(
            'class' => 'User',
            'table_name' => 'users',
            'fields' => 'uuid,name,role,created,modified'
        ),
        2 => array(
            'class' => 'AddOns',
            'table_name' => 'addons',
            'fields' => 'uuid,name,image_path,price,created,modified'
        ),
        3 => array(
            'class' => 'Transaction',
            'table_name' => 'transactions',
            'fields' => 'product_uuid,addon_uuid,user_uuid,total,created,modified',
            'associated_table' => array(
                                    'products' => 'name,prices,sizes,customized',
                                    'addons' => 'name,price',
                                    'users' => 'name,role'
                                )
        )
    );
}

function generate_class() {
    $output_model_dir = dirname(__DIR__, 1) . '\\objects';
    $models = get_model_array();
    
    foreach ($models as $model) {
        $class = $model['class'];
        $table_name = $model['table_name'];
        
        // {$model}.php
        $exploded_fields = explode(",", $model['fields']);
        $fields = "";

        foreach ($exploded_fields as $field)
            $fields .= "\t\t\t\t\t\t\t\t\t\t\t\"{$field}\" => \"\",\n";
            
        // Remove trailing "," and "\n".
        $fields = substr($fields, 0, strlen($fields) - 2);

        $class_file = fopen("{$output_model_dir}\\{$class}.php", "w");
        $content = <<<EOT
                    <?php
                    require_once "Crud.php";
                                
                    class {$class} extends Crud {
                        /**
                         * {$class} constructor.
                         * 
                         * @param \$db_connection -> Establish database connection.
                         * @param \$data -> Fields for the particular model. 
                         *                  Default \$data are for read and read_one requests.
                         */
                        public function __construct(\$db_connection,
                                                    \$data = array(\n{$fields})) {
                            parent::__construct(\$db_connection, "{$table_name}", \$data);
                        }
                    }
                    ?>
                    EOT;
        
        fwrite($class_file, $content);
        fclose($class_file);

        // CRUD dir.
        $dir = dirname(__DIR__, 1) . "\\{$table_name}";
        if (!is_dir($dir)) mkdir($dir);

        // {$model}\create.php
        generate_create($class, $dir);

        $associated_table = empty($model['associated_table']) ? null : $model['associated_table'];

        // {$model}\read.php
        generate_read($class, $dir, $exploded_fields, $associated_table);

        // {$model}\read_one.php
        generate_read_one($class, $dir, $exploded_fields, $associated_table);

        // {$model}\update.php
        generate_update($class, $dir);

        // {$model}\delete.php
        generate_delete($class, $dir);
    }
}

function generate_create($class, $dir) {
    $object_name = strtolower($class);
    $output_create_dir = "{$dir}\\create.php";

    $create_file = fopen($output_create_dir, "w");
    $content = <<<EOT
                <?php
                header("Access-Control-Allow-Origin: *");
                header("Content-Type: application/json; charset=UTF-8");
                header("Access-Control-Allow-Methods: POST");
                header("Access-Control-Max-Age: 3600");
                header("Access-Control-Allow-Headers: Content-Type, Access-Control-Headers, Authorization, X-Requested-With");

                include_once '../config/Database.php';
                include_once '../common/Util.php';
                include_once '../objects/{$class}.php';

                \$database = new Database();
                \$db_connection = \$database->get_connection();

                \$util = new Util();

                // Get POSTed data.
                \$data = json_decode(file_get_contents("php://input"), true);

                if (!\$util->has_empty_property(\$data)) {
                    \${$object_name} = new {$class}(\$db_connection, \$data);

                    if (\${$object_name}->create())
                        http_response_code(201); // CREATED response.
                    else // Failed to create {$class}.
                        http_response_code(503); // SERVICE UNAVAILABLE response.
                } else // Incomplete data.
                    http_response_code(400); // BAD REQUEST response.
                ?>
                EOT;

    fwrite($create_file, $content);
    fclose($create_file);
}

function generate_read($class, $dir, $exploded_fields, $associated_table) {
    $object_name = strtolower($class);
    $output_read_dir = "{$dir}\\read.php";

    $fields = get_imploded_fields($exploded_fields, "\t\t\t\t\t");

    $associated = get_associated_table_param($associated_table, "\t\t\t\t\t");

    $read_file = fopen($output_read_dir, "w");
    $content = <<<EOT
                <?php
                header("Access-Control-Allow-Origin: *");
                header("Content-Type: application/json; charset=UTF-8");
                
                include_once '../config/Database.php';
                include_once '../objects/{$class}.php';
                
                \$database = new Database();
                \$db_connection = \$database->get_connection();
                
                \${$object_name} = new {$class}(\$db_connection{$associated});
                
                \$stmt = \${$object_name}->read();
                
                if (\$stmt != null && \$stmt->rowCount() > 0) {
                    \${$object_name}s_arr = array();
                
                    while(\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
                        extract(\$row);
                
                        \${$object_name}_item = array(
                {$fields}
                        );
                
                        array_push(\${$object_name}s_arr, \${$object_name}_item);
                    }
                
                    http_response_code(200); // OK response.
                    echo json_encode(array('records' => \${$object_name}s_arr));
                } else { // No {$class} found.
                    http_response_code(404); // NOT FOUND response.
                
                    echo json_encode(array('message' => "No {$class} found."));
                }
                ?>
                EOT;

    fwrite($read_file, $content);
    fclose($read_file);
}

function generate_read_one($class, $dir, $exploded_fields, $assotiated_table) {
    $object_name = strtolower($class);
    $output_read_one_dir = "{$dir}\\read_one.php";

    $fields = get_imploded_fields($exploded_fields, "\t\t\t\t");
    
    $associated = get_associated_table_param($assotiated_table, "\t\t\t\t");

    $read_one_file = fopen($output_read_one_dir, "w");
    $content = <<<EOT
                <?php
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Header: access");
                header("Access-Control-Allow-Method: GET");
                header("Access-Control-Allow-Credentials: true");
                header("Content-Type: application/json");
                
                include_once '../config/Database.php';
                include_once '../objects/{$class}.php';
                
                \$database = new Database();
                \$db_connection = \$database->get_connection();
                
                \${$object_name} = new {$class}(\$db_connection, {$associated});
                \$uuid = isset(\$_GET['uuid']) ? \$_GET['uuid'] : die();
                \$stmt = \${$object_name}->read(\$uuid);
                
                if (\$stmt != null) {
                    \$row = \$stmt->fetch(PDO::FETCH_ASSOC);
                    extract(\$row);
                
                    \${$object_name}_item = array(
                {$fields}
                    );
                
                    http_response_code(200); // OK response.
                
                    echo json_encode(array('records' => \${$object_name}_item));
                } else { // No {$class} found.
                    http_response_code(404); // NOT FOUND response.
                
                    echo json_encode(array('message' => "No {$class} found."));
                }
                ?>
                EOT;

    fwrite($read_one_file, $content);
    fclose($read_one_file);
}

function generate_update($class, $dir) {
    $object_name = strtolower($class);
    $output_update_dir = "{$dir}\\update.php";

    $update_file = fopen($output_update_dir, "w");
    $content = <<<EOT
                <?php
                header("Access-Control-Allow-Origin: *");
                header("Content-Type: application/json; charset=UTF-8");
                header("Access-Control-Allow-Methods: POST");
                header("Access-Control-Max-Age: 3600");
                header("Access-Control-Allow-Headers: Content-Type, Access-Control-Headers, Authorization, X-Requested-With");
                
                include_once '../config/Database.php';
                include_once '../common/Util.php';
                include_once '../objects/{$class}.php';
                
                \$database = new Database();
                \$db_connection = \$database->get_connection();
                
                \$util = new Util();
                
                // Get POSTed data.
                \$data = json_decode(file_get_contents("php://input"), true);
                
                if (!empty(\$data['uuid'])) {
                    \${$object_name} = new {$class}(\$db_connection, \$data);
                
                    if (\${$object_name}->update())
                        http_response_code(200); // OK response.
                    else // Failed to update {$class}.
                        http_response_code(503); // SERVICE UNAVAILABLE response.
                } else
                    http_response_code(400); // BAD REQUEST response.
                ?>
                EOT;

    fwrite($update_file, $content);
    fclose($update_file);
}

function generate_delete($class, $dir) {
    $object_name = strtolower($class);
    $output_delete_dir = "{$dir}\\delete.php";

    $delete_file = fopen($output_delete_dir, "w");
    $content = <<<EOT
                <?php
                header("Access-Control-Allow-Origin: *");
                header("Content-Type: application/json; charset=UTF-8");
                header("Access-Control-Allow-Methods: POST");
                header("Access-Control-Max-Age: 3600");
                header("Access-Control-Allow-Headers: Content-Type, Access-Control-Headers, Authorization, X-Requested-With");
                
                include_once '../config/Database.php';
                include_once '../objects/{$class}.php';
                
                \$database = new Database();
                \$db_connection = \$database->get_connection();
                
                // Get POSTed data.
                \$data = json_decode(file_get_contents("php://input"), true);
                
                if (!empty(\$data['uuid'])) {
                    \${$object_name} = new {$class}(\$db_connection, \$data);
                
                    if (\${$object_name}->delete())
                        http_response_code(200); // OK response.
                    else // Failed to create {$class}.
                        http_response_code(503); // SERVICE UNAVAILABLE response.
                } else // Incomplete data
                    http_response_code(400); // BAD REQUEST response.
                ?>
                EOT;

    fwrite($delete_file, $content);
    fclose($delete_file);
}

function get_imploded_fields($fields, $tabs) {
    $result = "";
    foreach ($fields as $field) {
        $result .= "$tabs'$field' => \${$field}";
        if ($field == "customized") $result .= " == 0 ? false : true,\n";
        else $result .= ",\n";
    }

    // Remove trailing "," and "\n".
    return substr($result, 0, strlen($result) - 2);
}

function get_associated_table_param($associated_table, $tabs) {
    if ($associated_table == null) return "";
    
    $result = ", array(\n";
    foreach ($associated_table as $key => $table)
        $result .= "$tabs'$key' => '$table',\n";

    // Remove trailing "," and "\n".
    return substr($result, 0, strlen($result) - 2) . ")";
}

generate_class();
?>