<?php
require_once __DIR__ . '/../includes/ai-assistant-response.php';
function same($expected, $actual, $label) { if ($expected !== $actual) throw new RuntimeException($label . ': ' . var_export($actual, true)); echo "PASS: {$label}\n"; }
function denied($fn, $status, $label) { try { $fn(); } catch (PrismaAssistantException $e) { same($status, $e->status, $label); return; } throw new RuntimeException('Expected denial: ' . $label); }
function ids($result, $type) { $ids = array_map(fn($r) => $r['id'], array_filter($result['records'], fn($r) => $r['type'] === $type)); sort($ids); return array_values($ids); }
$dsn = getenv('PRISMA_QUERY_TEST_DSN') ?: 'sqlite::memory:';
if (strpos($dsn, 'mysql:') === 0 && !preg_match('/dbname=prisma_ia3_test(?:;|$)/', $dsn)) throw new RuntimeException('Use only isolated prisma_ia3_test.');
class AssistantQueryTestPDO extends PDO
{
    public function exec(string $statement): int|false
    {
        if ($this->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $statement = str_replace('INTEGER PRIMARY KEY', 'INT AUTO_INCREMENT PRIMARY KEY', $statement);
            $statement = str_replace(['created_at TEXT DEFAULT CURRENT_TIMESTAMP', 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP'], ['created_at DATETIME DEFAULT CURRENT_TIMESTAMP', 'updated_at DATETIME DEFAULT CURRENT_TIMESTAMP'], $statement);
        }
        return parent::exec($statement);
    }
}
$db = new AssistantQueryTestPDO($dsn, strpos($dsn,'mysql:')===0?'root':null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') foreach(['users','apps','requests','request_assignments','request_comments','request_checklist_items','tasks','task_shares','notes','note_shares'] as $table) $db->exec('DROP TABLE IF EXISTS '.$table);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, full_name TEXT)');
$db->exec("INSERT INTO users VALUES (7,'david','David'),(8,'ana','Ana Ruiz'),(9,'rebecca','Rebecca Soler'),(10,'otra','Otra Empresa')");
$db->exec('CREATE TABLE apps (id INTEGER PRIMARY KEY, name TEXT, company_id INTEGER)');
$db->exec("INSERT INTO apps VALUES (1,'Reservas',1),(2,'Inventario',1),(3,'Privada',2)");
$db->exec('CREATE TABLE requests (id INTEGER PRIMARY KEY, app_id INTEGER, title TEXT, description TEXT, status TEXT, priority TEXT, due_date TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, completed_at TEXT, requester_email TEXT)');
$db->exec("INSERT INTO requests(id,app_id,title,description,status,priority,due_date,completed_at,requester_email) VALUES
 (11,1,'Exportar reservas','Filtro para móvil','pending','high','2026-11-01',NULL,'NO_COMPARTIR@example.test'),
 (12,1,'Cambiar el listado','Revisar el orden','in_progress','medium','2026-11-04',NULL,NULL),
 (13,1,'Sin asignar','Revisar','pending','low',NULL,NULL,NULL),
 (14,1,'Terminado','Hecho','completed','medium','2026-10-30','2026-11-02 08:00:00',NULL),
 (15,3,'Mejora secreta','Nunca visible','pending','high',NULL,NULL,NULL),
 (16,2,'Inventario nuevo','Exportar existencias','pending','medium','2026-11-02',NULL,NULL),
 (17,1,'Descartada','No hacer','discarded','medium',NULL,NULL,NULL)");
$db->exec('CREATE TABLE request_assignments (id INTEGER PRIMARY KEY, request_id INTEGER, user_id INTEGER)');
$db->exec('INSERT INTO request_assignments(request_id,user_id) VALUES(11,7),(12,8),(14,7),(15,7),(16,7),(17,7)');
$db->exec('CREATE TABLE request_comments (id INTEGER PRIMARY KEY, request_id INTEGER, user_id INTEGER, content TEXT, created_at TEXT)');
$db->exec("INSERT INTO request_comments VALUES (1,11,8,'Decidido: conservar el filtro','2026-11-01 10:00:00'),(2,11,7,'Pendiente: probar el pago','2026-11-02 09:00:00'),(3,15,10,'Comentario secreto','2026-11-02 09:00:00')");
$db->exec('CREATE TABLE request_checklist_items (id INTEGER PRIMARY KEY, request_id INTEGER, title TEXT, position INTEGER, is_completed INTEGER)');
$db->exec("INSERT INTO request_checklist_items VALUES(1,11,'Probar en móvil',1,0)");
$db->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, app_id INTEGER, title TEXT, description TEXT, user_id INTEGER, company_id INTEGER, is_shared INTEGER, is_completed INTEGER, due_date TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, completed_at TEXT)');
$db->exec("INSERT INTO tasks(id,app_id,title,description,user_id,company_id,is_shared,is_completed,due_date) VALUES
 (1,1,'Privada propia','Preparar',7,1,0,0,NULL),(2,1,'Delegada','Para Rebecca',7,1,0,0,NULL),
 (3,1,'Equipo propia','Compartida',7,1,1,0,NULL),(4,1,'Para mí de Rebecca','Revisar',9,1,0,0,'2026-11-02'),
 (5,1,'Para Ana y para mí','Coordinar',9,1,0,0,NULL),(6,1,'Equipo de Ana','Revisar',8,1,1,0,NULL),
 (7,2,'Delegada a dos','Revisar',7,1,0,0,NULL),(8,1,'Privada de Ana','Secreto',8,1,0,0,NULL),
 (9,3,'Equipo de otra empresa','Secreto',10,2,1,0,NULL),(10,1,'Completada','Hecho',7,1,0,1,NULL),
 (11,3,'Compartida entre empresas','Visible solo por detalle',10,2,0,0,NULL)");
$db->exec('CREATE TABLE task_shares (task_id INTEGER,user_id INTEGER)');
$db->exec('INSERT INTO task_shares VALUES(2,9),(4,7),(5,7),(5,8),(7,8),(7,9),(11,7)');
$db->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY,app_id INTEGER,title TEXT,content TEXT,user_id INTEGER,company_id INTEGER,is_shared INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO notes(id,app_id,title,content,user_id,company_id,is_shared) VALUES
 (300,1,'Apuntes propios','Acuerdos del proyecto',7,1,0),(301,1,'Nota de equipo','Revisar el filtro',8,1,1),
 (302,1,'Compartida por nombre','Acciones pendientes',9,1,0),(303,1,'Privada de Ana','SECRETO_NOTA',8,1,0),
 (304,3,'Otra empresa','SECRETO_EMPRESA',10,2,1),(305,2,'Inventario','Recuento mensual',7,1,0)");
$db->exec('CREATE TABLE note_shares (note_id INTEGER,user_id INTEGER)');
$db->exec('INSERT INTO note_shares VALUES(302,7)');
$actor = ['id'=>7,'company_id'=>1,'role'=>'admin']; $apps = [1,2];
$resolver = static function($type,$id) use($db,&$apps) {
    if (in_array($type,['scope_mine','scope_team'],true)) return $id===7 ? ['label'=>'Mi trabajo','access_fingerprint'=>hash('sha256',json_encode($apps))] : null;
    if ($type==='app') return in_array($id,$apps,true) ? ['label'=>'App','access_fingerprint'=>hash('sha256','app'.$id)] : null;
    $table=['request'=>'requests','task'=>'tasks','note'=>'notes'][$type]??null;if(!$table)return null;
    $s=$db->prepare('SELECT * FROM '.$table.' WHERE id=?');$s->execute([$id]);$row=$s->fetch();if(!$row)return null;
    if($type==='request')$visible=in_array((int)$row['app_id'],$apps,true);
    else {
        $shares=$type==='task'?'task_shares':'note_shares';$s=$db->prepare('SELECT 1 FROM '.$shares.' WHERE '.$type.'_id=? AND user_id=7');$s->execute([$id]);
        $visible=$row['user_id']==7||($row['company_id']==1&&$row['is_shared'])||$s->fetchColumn();
    }
    return $visible ? ['label'=>$row['title'],'access_fingerprint'=>hash('sha256',$type.$id)] : null;
};
$q = new PrismaAssistantQueries($db,$actor,$apps,$resolver);
$mine=['scope'=>'mine'];$team=['scope'=>'team'];
$r=$q->run([], assistant_context($mine,'admin'),'2026-11-02');
same([11,16],ids($r,'request'),'mine: only active assigned requests inside allowed apps');
same([1,4,5],ids($r,'task'),'mine: private own and named to me, excluding delegated/team');
$r=$q->run([], $team,'2026-11-02');
same([11,12,13,16],ids($r,'request'),'team: every visible active request');
same([1,2,3,4,5,6,7],ids($r,'task'),'team: visible tasks, including delegated and team, excluding private of others');
$r=$q->run(['resource'=>'note'], $mine,'2026-11-02');same([300,301,302,305],ids($r,'note'),'notes: own and shared only');
same(false,strpos(json_encode($r),'SECRETO_')!==false,'private note content excluded from retrieval');
$r=$q->run([], ['scope'=>'app','entity_id'=>1],'2026-11-02');same([11,12,13],ids($r,'request'),'app context cannot expand to another app');
$r=$q->run(['app_name'=>'Inventario'], ['scope'=>'app','entity_id'=>1],'2026-11-02');same([],ids($r,'request'),'named app filter cannot escape fixed app context');
$r=$q->run(['person'=>'Ana'], $team,'2026-11-02');same([12],ids($r,'request'),'person matches visible request assignee');same([5,7],ids($r,'task'),'person matches task recipients and excludes team tasks');
$r=$q->run([], ['scope'=>'team','filters'=>['person'=>'none']],'2026-11-02');same([13],ids($r,'request'),'unassigned filter matches request assignment table');same([],ids($r,'task'),'unassigned filter does not misclassify private tasks');
$r=$q->run(['due'=>'overdue'], $team,'2026-11-02');same([11],ids($r,'request'),'overdue date uses user current day');
$r=$q->run(['due'=>'today'], $team,'2026-11-02');same([16],ids($r,'request'),'today date exact');same([4],ids($r,'task'),'today task exact');
$r=$q->run(['status'=>'completed','date_field'=>'completed','from'=>'2026-11-02','to'=>'2026-11-02'], $team,'2026-11-02');same([14],ids($r,'request'),'completion date filters completed work');
$r=$q->run(['query'=>'conservar'], $team,'2026-11-02');same([11],ids($r,'request'),'search includes authorized comments');
$db->exec("UPDATE requests SET updated_at='2020-01-01 00:00:00' WHERE id=13");
$db->exec("INSERT INTO request_comments VALUES(4,13,8,'Se ha retomado hoy','2026-11-02 11:00:00')");
$activity=$q->run(['resource'=>'request','date_field'=>'updated','from'=>'2026-11-02','to'=>'2026-11-02'],$team,'2026-11-02');
same(true,in_array(13,ids($activity,'request'),true),'recent comment prevents an old request from appearing inactive');
$r=$q->run(['query'=>"%' OR 1=1 --"], $team,'2026-11-02');same([],ids($r,'request'),'query text is escaped and parameterized');
$detail=$q->run(['mode'=>'detail','resource'=>'request','id'=>11],['scope'=>'request','entity_id'=>11],'2026-11-02');
same(2,count($detail['records'][0]['comments']),'detail includes comments');same(1,count($detail['records'][0]['checklist']),'detail includes checklist');
same(false,strpos(json_encode($detail),'NO_COMPARTIR')!==false,'requester contact data never enters model inputs');
$r=$q->run(['mode'=>'detail','resource'=>'request','id'=>15], $team,'2026-11-02');same([],ids($r,'request'),'guessed inaccessible ID returns no data');
$r=$q->run(['mode'=>'detail','resource'=>'task','id'=>11],['scope'=>'task','entity_id'=>11],'2026-11-02');same([11],ids($r,'task'),'explicit shared context uses direct access even across companies');
denied(fn()=>$q->run(['sql'=>'DROP TABLE tasks'],$team,'2026-11-02'),400,'model cannot introduce raw SQL fields');
denied(fn()=>$q->run(['from'=>'2026-02-30'],$team,'2026-11-02'),400,'invalid model date rejected before query');
denied(fn()=>$q->run([],['scope'=>'app','entity_id'=>3],'2026-11-02'),403,'inaccessible context rejected before search');

for($i=0;$i<25;$i++)$db->exec("INSERT INTO notes(id,app_id,title,content,user_id,company_id,is_shared) VALUES(".(400+$i).",1,'Nota $i','Texto',7,1,0)");
$page=$q->run(['resource'=>'note','sort'=>'recent'],$mine,'2026-11-02');same(20,count($page['records']),'search bounded to 20 per type');same(true,$page['partial'],'coverage marks partial result');same(true,$page['coverage'][0]['has_more'],'extra row detects more matches without inventing totals');
same(21,count($page['sources']),'has_more row is also an access dependency');
$page2=$q->run(['resource'=>'note','sort'=>'recent','offset'=>20],$mine,'2026-11-02');same([],array_values(array_intersect(array_column($page['records'],'id'),array_column($page2['records'],'id'))),'query offset retrieves a different page');
$db->exec("UPDATE notes SET content='".str_repeat('x',15000)."' WHERE id=300");
$r=$q->run(['mode'=>'detail','resource'=>'note','id'=>300],['scope'=>'note','entity_id'=>300],'2026-11-02');same(true,$r['partial'],'long note truncation disclosed');same(true,mb_strlen($r['records'][0]['content'])<12100,'long note bounded');

$formatted=assistant_format_answer(['data'=>['text'=>'Pendiente [[request:11]]. Falsa [[task:999]].','source_keys'=>['request:11','task:999']],'model'=>'fixture','usage'=>[]],$detail);
same(['request:11'],array_column($formatted['metadata']['references'],'key'),'only retrieved references may become links');
same('/index.php?open_request=11',$formatted['metadata']['references'][0]['url'],'server constructs record URL');
same(false,strpos($formatted['content'],'[[task:999]]')!==false,'hallucinated reference marker removed');
same(true,strpos($formatted['content'],'[referencia no disponible]')!==false,'missing evidence is explicit');
echo "Read-only assistant queries passed on " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ".\n";
