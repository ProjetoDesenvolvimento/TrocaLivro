<?php

namespace App\Http\Controllers;

use App\Notification;
use App\Troca;
use Illuminate\Http\Request;
use App\Http\Requests;
use Auth;
use DB;
use App\Livro;

use App\Usuario;

use App\LivroUsuario;
use Illuminate\Support\Facades\Session;

use App\Models\framework\GestorLibros;

use View;
//use App\Models\Livro;

class LivroController extends Controller
{

    private $livro;

    public function __construct(Livro $livro){
        $this->livro = $livro;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $livros = $this->livro->all();
        return view('livros.index', ['livros' => $livros]);
    }

    /**
     * Mostra o formulario para cadastrar um novo livro.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCreate()
    {
        return view("livros.cadastro");
    }

    public function getMyBooks() {
        $user = Auth::user();

        $livros = LivroUsuario::join("livro","livro.id","=","livrousuario.livro_id")
            ->where("usuario_id", "=", $user->id)->get();

        return view("livros.show",["livros"=> $livros]);
    }

    /**
     * Lista os livros cadastrados e disponiveis para troca
     *
     * @return \Illuminate\Http\Response
     */
    public function getShow() {
        if (Auth::check()){
            $user = Auth::user();
        }

        $livros = LivroUsuario::select(DB::Raw('count(livro.id) as total, livro.*'))
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id");

        if (isset($user))
            $livros->where("livrousuario.usuario_id", "=", $user->id);

        $livros = $livros->groupby("livro.id")->get();
        //select l.id,l.titulo, usuario.id from livrousuario join livro l ON l.id = livrousuario.livro_id join usuario ON usuario.id = livrousuario.usuario_id where usuario.id != 7
//echo "auuiiiiiiiiiiiiiiiiiiii!°".var_dump($livros).$user->id;;
        return view("livros.show",["livros"=> $livros]);

    }


    public function meusLivros() {
        $user = Auth::user();
        $livros = LivroUsuario::select('livro.*', 'livrousuario.id as livrousuario_id', 'usuario.nome as usuario_nome')
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id")
            ->where("usuario.id","=", $user->id)
            ->get();
        return $livros;
    }

    /**
     * Lista os livros cadastrados e disponiveis do usuario
     *
     * @return \Illuminate\Http\Response
     */
    public function getMeusLivros() {
        $livros = $this->meusLivros();
        //select l.id,l.titulo, usuario.id from livrousuario join livro l ON l.id = livrousuario.livro_id join usuario ON usuario.id = livrousuario.usuario_id where usuario.id != 7

        return view("livros.meusLivros",["livros"=> $livros]);

    }

    /**
     * Lista os livros cadastrados e disponiveis para troca
     *
     * @return \Illuminate\Http\Response
     */
    public function getShowBookByUser($id=0) {
        if (empty($id)) {
            echo "Livro não cadastrado";
            return;
        }
        $user = Auth::user();
        $livros = LivroUsuario::select('livro.*', 'livrousuario.id as livrousuario_id','livrousuario.estado as estado', 'usuario.nome as usuario_nome')
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id")
            ->where("livrousuario.livro_id", "=", $id)
            ->where("usuario.id","!=", $user->id)
            ->get();
        //select l.id,l.titulo, usuario.id from livrousuario join livro l ON l.id = livrousuario.livro_id join usuario ON usuario.id = livrousuario.usuario_id where usuario.id != 7

        return view("livros.showByUser",["livros"=> $livros]);

    }

    public function getBooksByUser($idusuario=0) {
        if (empty($idusuario)) {
            echo "Usuario nao existe";
            return;
        }
        $user = new Usuario();
        $user->id=$idusuario;
        $livros = LivroUsuario::select('livro.*', 'livrousuario.id as livrousuario_id', 'usuario.nome as usuario_nome')
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id")
            ->where("usuario.id","!=", $user->id)
            ->get();
        //select l.id,l.titulo, usuario.id from livrousuario join livro l ON l.id = livrousuario.livro_id join usuario ON usuario.id = livrousuario.usuario_id where usuario.id != 7

        return view("livros.booksbyuser",["livros"=> $livros,"usuarionome"=>$user->id]);

    }
    public function getTrocar() {
        return view("livros.trocar")->with("livros", array('a','b','c'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    private function getBookFromRequest(Request $request){
        $livro = new Livro();
        $livro->isbn=$request->get("isbn");
        $livro->idgb=$request->get("idgb", "nada2");
        $livro->titulo = $request->get("titulo");
        $livro->titulosearch = strtoupper($request->get("titulo"));
        $livro->descricao = $request->get("descricao");
        $livro->ano = $request->get("ano");
        $livro->paginas = $request->get("paginas");
        $livro->imagemurl = $request->get("imagemurl","#");
        $livro->created_at=date('Y-m-d G:i:s');
        $livro->updated_at=date('Y-m-d G:i:s');

        return $livro;
    }

    public function store(Request $request)
    {

        $gestor=new GestorLibros();
        $livro = DB::table('livro')->select('id', 'isbn','idgb','titulo','descricao','ano','paginas','imagemurl')
            ->where('isbn', "=", $request->get("isbn"))->first();
        if(!$livro){   //si no existe el livro
            $livro=$this->getBookFromRequest($request);
            $autores=$request->get("autores");
            $gestor->cadastrarLivro($livro);
            if (!empty($autores)) {
                $gestor->cadastrarAutoresLivro($autores, $livro);//AUTORES LIVROS CADASTRO
            }
        }
        $user_id = Auth::user()->id;
        if(!LivroUsuario::where('usuario_id', '=', $user_id)->where('livro_id', '=', $livro->id)->exists()){
            $lu = new LivroUsuario();
            $lu->usuario_id=$user_id;
            $lu->livro_id=$livro->id;
            $lu->estado=$request->get("estadolivro");
            $lu->save();

        }else{
            //  echo "el usuario ya lo tiene";
        }
        return View::make('livros.cadastrolivrosuceso', array('livro' => $livro));
    }


    public function getSolicitarTroca($book_id) {
        $livro = LivroUsuario::select('livro.*', 'livrousuario.id as livrousuario_id', 'usuario.id as usuario_id', 'usuario.nome as usuario_nome')
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id")
            ->where("livrousuario.id", "=", $book_id)
            ->first();
        return view("livros.solicitar", ['result'=>$livro]);
    }

    public function getSolicitarTrocaUsuario($book_id) {
        $livrosusuarioid= LivroUsuario::select("id")->where("usuario_id","=",Auth::user()->id)->get();
        $arrayitemsid=array();
        array_push($arrayitemsid,-1);
        foreach($livrosusuarioid as $id){
         array_push($arrayitemsid,$id[0]);
        // echo "entreee  ";
        }
        $troca = Troca::where('solicitacao_A','=', $book_id)->where('idsolicitante',"=",Auth::user()->id)->get();
        //var_dump($troca);
        if (!$troca||count($troca)==0) {
            $troca = new Troca();
            $troca->solicitacao_A = $book_id;
            $troca->idsolicitante = Auth::user()->id;
            $troca->estado = 1;
            $troca->save();


            //notificar ao usuario sobre a troca feita...
            $notification=new Notification();



            $usuario = Usuario::where("id","=",LivroUsuario::where("id","=",$book_id)->first()->usuario_id)->first();
           // var_dump($usuario);
            $notificacao = new Notification();
            $notificacao->texto = "O usuário ".Auth::user()->nome." solicitou a troca de um livro seu.";
            $notificacao->tipo = 1;
            $notificacao->emailorigen =  Auth::user()->email;
            $notificacao->emailobjeti = $usuario->email;
            $notificacao->estado = 1;
            $notificacao->save();


        }else{
            return view("livros.jaSolicitado");
        }
        return view("livros.solicitado" );//fazer a view um pouco mas bonita
    }

    public function postTenho(Request $request) {
        return $this->cadastrarLivroUsuario($request);
    }

    public function cadastrarLivroUsuario(Request $request)
    {
        $gestor=new GestorLibros();
        $livro = DB::table('livro')->select('id', 'isbn','idgb','titulo','descricao','ano','paginas','imagemurl')
            ->where('idgb', $request->get("idgb", "cadastro-manual"))->first();
        if(!$livro){
            $livro = $this->getBookFromRequest($request);

            $autores=$request->get("autores");
            $gestor->cadastrarLivro($livro);
            $gestor->cadastrarAutoresLivro($autores, $livro);//AUTORES LIVROS CADASTRO

        }
        $user = Auth::user();

        if(!LivroUsuario::where('usuario_id', '=', $user->id)->where('livro_id', '=', $livro->id)->exists()){

            $lu=new LivroUsuario();
            $lu->usuario_id=$user->id;
            $lu->livro_id=$livro->id;
            $lu->estado=$request->get("estadolivro");;
            $lu->save();

        }else{
            echo "Já possui o livro";
        }

        return View::make('livros.cadastrolusuccess', array('livro' => $livro));
    }

    public function verdato(Request $request, $type){
        $type = isset($type) ? $type : "isbn";
        $data = isset($criteria) ? $criteria : "";
        $ini = isset($ini) ? $ini : 0;//inicio, offset
        $quan = isset($quan) ? $quan : 10;//quantidade, limit

        $criteria = $data = $request->get("q");


        if (is_numeric($criteria) && strlen($criteria) > 3){
            $type = "isbn";
        }else{
            $type = "title";
        }


        $livrosarray=array();
        switch ($type) {
            case 'isbn':
                $criteria_="isbn";
                break;

            case 'title':
                $criteria_="titulosearch";
                break;

            case 'description':
                $criteria_="descricao";
                break;

            default :
                $criteria_="idgb";
                break;
        }
        $livros=Livro::where($criteria_, 'LIKE', '%'.strtoupper($criteria).'%')->take(10)->get();

        $nlivros=count($livros);

        if($nlivros>0){
            foreach($livros as $liv){
                array_push($livrosarray,$liv);
            }
        }
        if($nlivros<10){
            //echo "menos de diez".$nlivros;
            $nlivros = 10 - $nlivros;

            $gestor = new GestorLibros();
            $livrosGB = array();
            switch ($type) {
                case 'isbn' :
                    $livrosGB = $gestor->searchBooksByISBN($data);
                    $crieteria = "isbn";
                    break;
                case 'title' :
                    $livrosGB = $gestor->searchBooksByTitle($data);
                    $criteria = "titulo";
                    break;
                case 'description' :
                    $livrosGB = $gestor->searchBooksByDescription($data);
                    $criteria = "descricao";
                    break;
                case 'year' :
                    $livrosGB = $gestor->searchBooksByAllCriteria($data);
                    break;
                case 'feed':
                    $user = new User();
                    $user->setIdusuario('');
                    $livrosGB = $gestor->getBooksToFeed($ini, $quan);
                    break;
                default :
                    break;
            }
            $livrosarray = array_merge($livrosarray, $livrosGB);
        }

        $arr = array();
        $arr["items"] = array();
        foreach ($livrosarray as $livro) {
            $arr["items"][] = array(
                "id"=>$livro->isbn,
                "idgb"=>$livro->idgb,
                "text"=>$livro->titulo,
                "title"=>$livro->titulo,
                "isbn"=> $livro->isbn,
                "description"=>$livro->descricao,
                "year"=>$livro->ano,
                "countPages"=>$livro->paginas,
                "link"=>$livro->linkPrevio,
                "authors"=>$livro->getAutores(),
                "publisher"=>$livro->editora,
                "smallThumbnail"=>$livro->imagemurl);
        }

        return json_encode($arr);
    }


    public function verdato2($type,$criteria){
        $livrosarray = $this->buscarLivros($type,$criteria);
        return View::make('livros.asinc_livrocadastro_posibilidades', array('livrosarray' => $livrosarray));
    }

    private function buscarLivros($type="",$criteria="", $start=0, $limit=10) {
        $type = isset($type) ? $type : "isbn";
        $data = isset($criteria) ? $criteria : "";
        $ini = isset($ini) ? $ini : 0;//inicio, offset
        $quan = isset($quan) ? $quan : 10;//quantidade, limit
        //  echo $type.$data;

        $livros=null;
        $livrosarray=array();
        $criteria_="titulo";

        //echo "datoos ".$type.$data;
        switch ($type) {
            case 'isbn' :
                //	echo "finnn";

                $criteria_="isbn";

                break;

            case 'title' :

                $criteria_="titulo";
                break;

            case 'description' :

                $criteria_="descricao";
                break;

            default :
                $criteria_="idgb";
                break;
        }
        $livros=Livro::where($criteria_, 'LIKE', '%'.$criteria.'%')->take($limit)->get();

        $nlivros=count($livros);

        if($nlivros>0){
            foreach($livros as $liv){
                array_push($livrosarray,$liv);
            }
        }
        if($nlivros<10){
            // echo "menos de diez".$nlivros;
            $nlivros=10-$nlivros;

            $gestor = new GestorLibros();

            switch ($type) {
                case 'isbn' :
                    //	echo "finnn";
                    $libros = $gestor -> searchBooksByISBN($data);
                    $crieteria="isbn";

                    break;

                case 'title' :
                    if (!empty($limit)) {
                        $gestor->maxResults = $limit;
                    }
                    if (!empty($start)) {
                        $gestor->startIndex = $start;
                    }
                    $libros = $gestor -> searchBooksByTitle($data);
                    $criteria="titulo";
                    break;

                case 'description' :
                    $libros = $gestor -> searchBooksByDescription($data);
                    $criteria="descricao";
                    break;
                case 'year' :
                    $libros = $gestor -> searchBooksByAllCriteria($data);
                    break;


                case 'feed':
                    $user=new User();
                    $user->setIdusuario('');
                    $libros = $gestor -> getBooksToFeed($ini,$quan);
                    break;

                default :
                    break;
            }

        }

        foreach ($libros as $livro) {
            // Se o tamanho do titulo for maior que 25 corta
            if (strlen($livro->titulo) > 25){
                $livro->titulo = substr ($livro->titulo,0,25) . '...';
            }
            array_push($livrosarray,$livro);
        }
        return $livrosarray;
    }

    public function getBuscar(Request $req, $startIndex=0,$limit=12) {

        $livros = $this->buscarLivros("title",$req->get("busca"), $startIndex, $limit);
        return View::make('livros.feed',
            array('livrosresult' => $livros,'start'=>$startIndex,'limit'=>$limit));
    }

    public function getFeed($startIndex=0,$limit=12){
        if (Auth::check()){
            $user = Auth::user();
        }

        $livros = LivroUsuario::select(DB::Raw('count(livro.id) as total, livro.*'))
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id")
            ->where("disponibilidade", "!=", "0");

        if (isset($user))
            $livros->where("livrousuario.usuario_id", "!=", $user->id);

        $livrosSGBD = $livros->groupby("livro.id")->skip($startIndex*$limit)->take($limit)->get();

        $livrosArray=array();

        foreach($livrosSGBD as $livro){
            // Se o tamanho do titulo for maior que 25 corta
            if (strlen($livro->titulo) > 25){
                $livro->titulo = substr ($livro->titulo,0,25) . '...';
            }

            if($livro->imagemurl == "") {
                $livro->imagemurl = "../images/capa_padrao.jpg";
            }

            array_push($livrosArray,$livro);

        }

        $gestor = new GestorLibros();
        $gestor->addFilter("langRestrict", "pt");
        $livrosGB = $gestor->getBooksToFeed($startIndex*$limit,$limit-count($livrosArray));
        foreach($livrosGB as $livro){
            if (strlen($livro->titulo) > 25){
                $livro->titulo = substr ($livro->titulo,0,25) . '...';
            }
            if($livro->imagemurl == "") {
                $livro->imagemurl = "../images/capa_padrao.jpg";
            }
            array_push($livrosArray, $livro);
        }


        return View::make('livros.feed',
            array('livrosresult' => $livrosArray,'start'=>$startIndex,'limit'=>$limit));

    }

    public function getTenho($idgb="", $book_id=0)
    {
        $gestor = new GestorLibros();
        if (empty($book_id)) {
            $livro = Livro::where("id", "=", $book_id)->select('id', 'isbn', 'idgb',
                'titulo', 'descricao', 'ano', 'paginas', 'imagemurl')->first();
        }
        if(!isset($livro) || empty($livro)){
            $gestor -> maxResults=1;
            $gestor ->updateFilters();
            $livros=$gestor->searchGBBooksById($idgb);
            if(count($livros)>0){
                $livro=$livros[0];
            }
            if(is_object($livro) && $livro->imagemurl == "") {
                $livro->imagemurl = "../images/capa_padrao.jpg";
            }
        }



        return View::make('livros.tenho', array('livro' => $livro));
    }

    public function getDestacados($startIndex=0,$limit=20){
        if (Auth::check()){
            $user = Auth::user();
        }

        $livros = LivroUsuario::select(DB::Raw('count(livro.id) as total, livro.*'))
            ->join("livro","livro.id", "=", "livrousuario.livro_id")
            ->join("usuario", "usuario.id","=","livrousuario.usuario_id")
            ->where("livrousuario.disponibilidade","!=", "0");

        if (isset($user))
            $livros->where("livrousuario.usuario_id", "!=", $user->id);

        $livrosSGBD = $livros->groupby("livro.id")->skip($startIndex*$limit)->take($limit)->get();

        $livrosArray=array();

        foreach($livrosSGBD as $livro){
            // Se o tamanho do titulo for maior que 25 corta
            if (strlen($livro->titulo) > 25){
                $livro->titulo = substr ($livro->titulo,0,25) . '...';
            }
            array_push($livrosArray,$livro);

        }

        $gestor = new GestorLibros();
        $gestor->addFilter("langRestrict", "pt");
        $livrosGB = $gestor->getBooksToFeed($startIndex*$limit,$limit-count($livrosArray));
        foreach($livrosGB as $livro){
            if (strlen($livro->titulo) > 25){
                $livro->titulo = substr ($livro->titulo,0,25) . '...';
            }
            if($livro->imagemurl == "") {
                $livro->imagemurl = "../images/capa_padrao.jpg";
            }
            array_push($livrosArray, $livro);
        }


        return View::make('livros.destacados',
            array('livrosresult' => $livrosArray,'start'=>$startIndex,'limit'=>$limit));

    }
}
