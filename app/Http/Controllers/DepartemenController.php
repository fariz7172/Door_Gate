<?php
namespace App\Http\Controllers;
use App\Models\Departemen;
use Illuminate\Http\Request;

class DepartemenController extends Controller {
    public function index(Request $r){
        $q = Departemen::query();
        if($r->filled('search')){
            $s=$r->search;
            $q->where(fn($qq)=>$qq->where('name','like',"%{$s}%")->orWhere('description','like',"%{$s}%")->orWhere('number','like',"%{$s}%"));
        }
        $departemens = $q->orderBy('id','desc')->paginate(12)->withQueryString();
        $stats = ['total'=>Departemen::count()];
        return view('departemen.index', compact('departemens','stats'));
    }
    public function create(){
        return view('departemen.form', ['d'=> new Departemen(), 'mode'=>'create']);
    }
    public function store(Request $r){
        $data=$r->validate([
            'number'=>'nullable|max:15',
            'name'=>'nullable|max:30',
            'description'=>'nullable|max:100',
        ]);
        Departemen::create($data);
        return redirect()->route('departemen.index')->with('ok','Departemen ditambahkan');
    }
    public function edit(Departemen $departeman){ // route binding 'departeman' due to auto singular
        return view('departemen.form', ['d'=>$departeman,'mode'=>'edit']);
    }
    public function update(Request $r, Departemen $departeman){
        $data=$r->validate([
            'number'=>'nullable|max:15',
            'name'=>'nullable|max:30',
            'description'=>'nullable|max:100',
        ]);
        $departeman->update($data);
        return redirect()->route('departemen.index')->with('ok','Departemen diperbarui');
    }
    public function destroy(Departemen $departeman){
        $departeman->delete();
        return back()->with('ok','Departemen dihapus');
    }
}
