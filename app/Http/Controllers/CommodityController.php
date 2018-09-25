<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Validator, Input, Redirect, Session;
use App\Category;
use App\Commodity;
use App\Stock;
use Auth;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CommodityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $commodities = Commodity::orderBy('created_at', 'desc')->get();

        $categories = Category::all();

        return view('commodities.index')
                    ->withCommodities($commodities)
                    ->withCategories($categories);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // COMMODITY PART
        //validation
        $this->validate($request, array(
          'category_id' => 'required|integer',
          'quantity'=>'required|numeric',
          'total'=>'required|numeric'
        ));
       
        //store to DB
        $commodity = new Commodity;
        $commodity->category_id = $request->category_id;
        $commodity->user_id = Auth::user()->id;
        $commodity->quantity = $request->quantity;
        $commodity->total = $request->total;
        $commodity->save();

        // STOCK PART
        //validation
        $stock = Stock::where('category_id', $request->category_id)->first();
        // dd($stock);
        if($stock !== null) {
          $this->validate($request, array(
              'category_id' => 'required|integer',
              'quantity'=>'required|numeric'
          ));
          //update to DB
          $stock->user_id = Auth::user()->id;
          $stock->quantity = $stock->quantity + $request->quantity;
        } elseif($stock == null) {
          $this->validate($request, array(
              'category_id' => 'required|integer|unique:stocks,category_id',
              'quantity'=>'required|numeric'
          ));
          //store to DB
          $stock = new Stock;
          $stock->category_id = $request->category_id;
          $stock->user_id = Auth::user()->id;
          $stock->quantity = $request->quantity;
        }
        
        $stock->save();
        // STOCK PART

        Session::flash('success', 'A new Commodity has been created successfully!');
        //redirect
        return redirect()->route('commodities.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $commodity = Commodity::find($id);
        
        $this->validate($request, array(
          'quantity'=>'required|numeric'
        ));
        //update to DB
        $commodity->user_id = Auth::user()->id;
        $oldquantity = $commodity->quantity;
        $commodity->quantity = $request->quantity;
        $commodity->total = $request->total;
        $commodity->save();

        // STOCK PART
        //validation
        $stock = Stock::where('category_id', $commodity->category_id)->first();
        $this->validate($request, array(
            'quantity'=>'required|numeric'
        ));
        //update to DB
        $stock->user_id = Auth::user()->id;
        $stock->quantity = $stock->quantity - $oldquantity + $request->quantity;
        $stock->save();
        // STOCK PART

        Session::flash('success', 'Updated successfully!');
        //redirect
        return redirect()->route('commodities.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $commodity = Commodity::find($id);
        $commodity->delete();
        
        // STOCK PART
        $stock = Stock::where('category_id', $commodity->category_id)->first();
        $stock->user_id = Auth::user()->id;
        $stock->quantity = $stock->quantity - $commodity->quantity;
        $stock->save();
        // STOCK PART

        Session::flash('success', 'Deleted successfully!');
        //redirect
        return redirect()->route('commodities.index');
    }

    public function getExpenditure() {
        $todaysexpense = DB::table('commodities')
                        ->select(DB::raw('SUM(total) as totalprice'))
                        ->whereDate('created_at', '>=', Carbon::today())
                        ->first();

        $thisyearsexpense = DB::table('commodities')
                        ->select('created_at', DB::raw('SUM(total) as totalprice'))
                        ->where(DB::raw("DATE_FORMAT(created_at, '%Y')"), "=", Carbon::now()->format('Y'))
                        ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                        ->orderBy('created_at', 'DESC')
                        ->get();

        $thismonthsexpense = DB::table('commodities')
                        ->select('created_at', DB::raw('SUM(total) as totalprice'))
                        ->where(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), "=", Carbon::now()->format('Y-m'))
                        ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d')"))
                        ->orderBy('created_at', 'DESC')
                        ->get();

        $lastsevendaysexpense = DB::table('commodities')
                        ->select('created_at', DB::raw('SUM(total) as totalprice'))
                        ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d')"))
                        ->orderBy('created_at', 'DESC')
                        ->take(7)
                        ->get();
        
        $datesforchart = [];
        foreach ($lastsevendaysexpense as $key => $days) {
            $datesforchart[] = date_format(date_create($days->created_at), "F d");
        }
        $datesforchart = json_encode(array_reverse($datesforchart));

        $totalsforchart = [];
        foreach ($lastsevendaysexpense as $key => $days) {
            $totalsforchart[] = $days->totalprice;
        }
        $totalsforchart = json_encode(array_reverse($totalsforchart));

        return view('commodities.expenditure')
                    ->withTodaysexpense($todaysexpense)
                    ->withThisyearsexpense($thisyearsexpense)
                    ->withThismonthsexpense($thismonthsexpense)
                    ->withDatesforchart($datesforchart)
                    ->withTotalsforchart($totalsforchart);
    }
}