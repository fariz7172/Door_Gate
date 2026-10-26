<?php
namespace App\Http\Controllers;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller {
    public function index(Request $r){
        $q = Branch::query();
        if($r->filled('search')){
            $s=$r->search;
            $q->where(fn($qq)=>$qq->where('name','like',"%{$s}%")->orWhere('description','like',"%{$s}%")->orWhere('number','like',"%{$s}%"));
        }
        $branches = $q->orderBy('number')->paginate(12)->withQueryString();
        $stats = ['total'=>Branch::count()];
        return view('branch.index', compact('branches','stats'));
    }
    public function create(){
        return view('branch.form', ['b'=> new Branch(), 'mode'=>'create']);
    }
    public function store(Request $r){
        $data=$r->validate([
            'number'=>'nullable|integer|unique:branch,number',
            'name'=>'nullable|max:30',
            'description'=>'nullable|max:100',
        ]);
        Branch::create($data);
        return redirect()->route('branch.index')->with('ok','Cabang ditambahkan');
    }
    public function edit(Branch $branch){
        return view('branch.form', ['b'=>$branch,'mode'=>'edit']);
    }
    public function update(Request $r, Branch $branch){
        $data=$r->validate([
            'number'=>'nullable|integer|unique:branch,number,'.$branch->id,
            'name'=>'nullable|max:30',
            'description'=>'nullable|max:100',
        ]);
        $branch->update($data);
        return redirect()->route('branch.index')->with('ok','Cabang diperbarui');
    }
    public function destroy(Branch $branch){
        $branch->delete();
        return back()->with('ok','Cabang dihapus');
    }
}
