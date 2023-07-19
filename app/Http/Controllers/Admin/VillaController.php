<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Villas;
use App\Models\Address;
use App\Models\Media;
use App\Models\Pricing;
use App\Models\Reservation;
use App\Models\Amenities;
use App\Models\VillaAmenities;
use App\Models\Servicelist;
use App\Models\Service;
use App\Models\CustomDetail;
use App\Models\Category;
use App\Models\Destination;
use App\Models\VillaIcs;
use Auth;
use DB;
use Illuminate\Support\Facades\File;
class VillaController extends AdminBaseController
{
   public function index(){
      $villas = Villas::with('address','media')->get();
      
      return view('Admin.villas.index',compact('villas'));
   }
   public function addvillas(){
      $categories = Category::get();
      $amenities = Amenities::get();
      $services = Servicelist::get();
      $destination = Destination::get();
      
    return view('Admin.villas.addvillas',compact('amenities','services','categories','destination'));
   }
   public function addProcc(Request $request){
     
      $services = Servicelist::get();
     
      $request->validate([
         'villaname' => 'required',
         'slug' => 'required|unique:villas',
         'street_name' => 'required',
         'city' => 'required',
         'state' => 'required',
         'country_name' => 'required',
         'min_guest' => 'required',
         'max_guest' => 'required',
         'cat_id' => 'required',
         'Longitude' => 'required',
         'Latitude' => 'required',
         'ics_url' => 'required',
         // 'images' => '',
      ]);

      $villas = new Villas;
      $villas->name = $request->villaname;
      $villas->user_id = Auth::user()->id;
      $villas->slug = $request->slug;
      $villas->description = $request->description;
      $villas->min_guest = $request->min_guest;
      $villas->max_guest = $request->max_guest;
      $villas->category_id = $request->cat_id;
      $villas->destination_id = $request->destination;
      $villas->save();

      if($villas->save()){
         $address = new Address;
         $address->villa_id = $villas->id;
         $address->street_name = $request->street_name;
         $address->city = $request->city;
         $address->state = $request->state;
         $address->country = $request->country_name;
         $address->longitude = $request->Longitude;
         $address->latitude = $request->Latitude;
         $address->save();
         
         if($request->hasFile('images')){
            $file = $request->file('images');
            foreach($file as $f){
              $extension = $f->getClientOriginalExtension();
              $name = 'villas_'.rand(0,1000).time().'.'.$extension;
              $f->move(public_path().'/villa_images/',$name);
              
              $media = new Media;
              $media->villa_id = $villas->id;
              $media->media_name = $name;
              $media->media_url = url('villa_images/'.$name);
              $media->save();
              $media_ids[] = $media->id; 
            }
         }else{
               $media = new Media;
               $media->villa_id = $villas->id;
               $media->media_name = 'default-image.jpg';
               $media->media_url = url('public/villa_images/'.'default-image.jpg');
               $media->save();
               $media_ids[] = $media->id;
         }
         $villas_update = Villas::find($villas->id);
         $villas_update->Location_id = $address->id;
         $villas_update->banner_id = $media_ids[0];
         $villas_update->update();

         if($request->amemities){
            foreach($request->amemities as $amenites){
               $aminites = new VillaAmenities;
               $aminites->villa_id = $villas->id;
               $aminites->amenitie_id = $amenites;
               $aminites->save();
            }
         }
         if($request->servicename){
            for ($i=0; $i < count($request->servicename); $i++) { 
               $service = new Service;
               $service->service_id = $services[$i]['id'];
               $service->value = $request->servicename[$i];
               $service->villa_id = $villas->id;
               $service->save();
            }
         }

         if($request->Customtitle){
            for ($i=0; $i < count($request->Customtitle) ; $i++) { 
               if($request->Customtitle[$i] == "" && $request->customedescription[$i] == ""){
                  continue;
               }
             $customedetail = new CustomDetail;
             $customedetail->title = $request->Customtitle[$i];
             $customedetail->description = $request->customedescription[$i];
             $customedetail->villa_id = $villas->id;
             $customedetail->save();
            }
         }
         $file_path = public_path('icsfiles/listingics_'.$villas->id.'.ics');
         $filename = 'listingics_'.$villas->id.'.ics';
         File::put($file_path,'test');
            $villasics = new VillaIcs;
            $villasics->villa_id = $villas->id;
            $villasics->ics_url = $request->ics_url;
            $villasics->file_name = $filename;
            $villasics->file_url = asset('icsfiles/'.$filename);
            $villasics->save();


         return redirect()->back()->with(['success'=>'successfully saved villas']);
      }else{
         return redirect()->back()->with(['error'=>'successfully saved villas']);
      }
   }
   public function villaView($slug){
         $villas = Villas::where('slug',$slug)->with('address','media')->first();
         $villas_pricing = Pricing::where('villa_id',$villas->id)->get();
         $aminites = VillaAmenities::where('villa_id',$villas->id)->with('amenitie')->get();
         $services = Service::where('villa_id',$villas->id)->with('service')->get();
         $all_animites = VillaAmenities::get();
         return view('Admin.villas.villaview',compact('villas','villas_pricing','all_animites','services','aminites'));
   }
   public function delete($id){
      $villas = Villas::find($id);
      if($villas){
         $villas->delete();
         return redirect('admin-dashboard/villas')->with('success','Success!Villas deleted successfully');
      }else{
         return redirect()->back()->with('error','Failed to delete!something went wrong');
      }
   }
   public function update(Request $request){
      if($request->val){
      // $slug = strtolower(str_replace(" ","-",$request->val));
      $update = Villas::find($request->id);
      $update->name = $request->val;
      // $update->slug = $slug;
      $update->update();
      return response()->json('successfully updated villas name');
      }
      // Update Image code is here
      elseif($request->image_id){
         $media = Media::find($request->image_id);
         if($request->hasFile('file')){
            ///////
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $name = 'villas_'.rand(0,1000).time().'.'.$extension;
            $file->move(public_path().'/villa_images/',$name);
            $update = Media::find($request->image_id);
            $update->media_name = $name;
            $update->media_url = url('villa_images/'.$name);
            $update->update();
            return redirect()->back()->with('success','Image has been updated');
         }
      }
      // Add Feature Image Code 
      elseif($request->featuredImage){
         $Villa = Villas::where("id",$request->villaId)->update(["banner_id" => $request->featuredImage]);
         return response()->json('Fetured image has successfully updated');
         // return response()->json($request->all());
      }
      // This is address update code
      else{
        $request->validate([
         'street' => 'required',
         'city' => 'required',
         'state' => 'required',
         'country_name' => 'required',
        ]);
        $update = Address::find($request->id);
        $update->street_name = $request->street;
        $update->city = $request->city;
        $update->state = $request->state;
        $update->country = $request->country_name;
        $update->update();
        return response()->json($update);
      }
   }
   public function calendar($id){
      $events = Reservation::where('villa_id',$id)->get();
     $data = array();
      foreach($events as $event){
      $data[] =  array(
         // 'id'       => $event->id,
         'title'    =>  $event->title,
         'start'    =>  $event->start,
         'end'      =>  $event->end,
         'status'   =>  '1',
         'description' => $event->descirption,
         'color'    =>  '#6294a7',
         'allDay'   =>  false,
     );
   }
      return response()->json($data);

   }
   
// }
   // Remove Image function 
   public function removeImage(Request $request){
      // return  response()->json($request->all());
      $mediaImage = Media::find($request->media_id);
      $image_path = $mediaImage->media_url;
      $image_path = public_path("/villa_images/".$mediaImage->media_name); 
      if(File::exists($image_path)) {
         File::delete($image_path);
         $mediaImage = Media::find($request->media_id);
         $mediaImage->delete();
      }else{
         return response()->json(false);
      }
      return response()->json(true);
   }

   public function addImage(Request $request) {
      // return response()->json($request->all());

      if ($request->hasFile('image')) {
          $file = $request->file('image');
          $extension = $file->getClientOriginalExtension();
          $name = 'villas_' . rand(0, 1000) . time() . '.' . $extension;
          $file->move(public_path() . '/villa_images/', $name);
          $media = new Media;
          $media->villa_id = $request->villaId;
          $media->media_name = $name;
          $media->media_url = url('villa_images/' . $name);
          $media->save();
          return response()->json('Image added successfully');
      }else{
         return response()->json(false);
      }
  }


  public function updateVilla(Request $request, $villaSlug){
   $villasData = Villas::where('slug',$villaSlug)->with('amenities','service','address','category','customedata','villasics')->first();
   // $villasData = Villas::where('slug',$villaSlug)->with('amenities','service','address')->first()->toArray();
   // echo '<pre>';
   // print_r($villasData);
   // echo '</pre>';
   // die();
   $categories = Category::get();
   $amenities = Amenities::get();
   $services = Servicelist::get();
   $destination = Destination::get();
   
   return view('Admin.villas.editVillas',compact('amenities','services','villasData','categories','destination'));
  }

  public function updateProcc(Request $request){

   // dd($viillas);
   $request->validate([
      'villaname' => 'required',
      'slug' => 'required|unique:villas,slug,' . $request->id,
      'street_name' => 'required',
      'city' => 'required',
      'state' => 'required',
      'country_name' => 'required',
      'min_guest' => 'required',
      'max_guest'=> 'required',
      'Longitude' => 'required',
      'Latitude' => 'required',
      'cat_id' => 'required',
      'ics_url' => 'required',
      // 'images' => '',
   ]);
   

   $villas = Villas::find($request->id);
   $villas->name = $request->villaname;
   $villas->slug = $request->slug;
   $villas->description = $request->description;
   $villas->min_guest = $request->min_guest;
   $villas->max_guest = $request->max_guest;
   $villas->category_id = $request->cat_id;
   $villas->destination_id = $request->destination;
   $villas->update();
   $villaAddress = Address::find($request->id);
   $villaAddress->city = $request->city;
   $villaAddress->street_name = $request->street_name;
   $villaAddress->state = $request->state;
   $villaAddress->country = $request->country_name;
   $villaAddress->longitude = $request->Longitude;
   $villaAddress->latitude = $request->Latitude;
   $villaAddress->update();

      $delaminites = VillaAmenities::where(['villa_id' => $request->id])->delete();
      $servicesAll = Service::where('villa_id', $request->id)->delete();
      $custome_data = CustomDetail::where('villa_id',$request->id)->delete();
      $services = Servicelist::get();

      if($request->amemities){
         foreach($request->amemities as $amenites){
            $aminites = new VillaAmenities;
            $aminites->villa_id = $villas->id;
            $aminites->amenitie_id = $amenites;
            $aminites->save();
         }
      }
      if($request->servicename){
         for ($i=0; $i < count($request->servicename); $i++) { 
            $service = new Service;
            $service->service_id = $services[$i]['id'];
            $service->value = $request->servicename[$i];
            $service->villa_id = $villas->id;
            $service->save();
         }
      }
      if($request->Customtitle){
         for ($i=0; $i < count($request->Customtitle) ; $i++) { 
          $customedetail = new CustomDetail;
          $customedetail->title = $request->Customtitle[$i];
          $customedetail->description = $request->customedescription[$i];
          $customedetail->villa_id = $villas->id;
          $customedetail->save();
         }
      }
       
      $icsupdate = VillaIcs::where('villa_id',$villas->id)->first();
      if($icsupdate != null){

      $icsupdatess = VillaIcs::find($icsupdate->id);
      $icsupdatess->ics_url = $request->ics_url;
      $icsupdatess->update();
      }else{
   
         $file_path = public_path('icsfiles/listingics_'.$villas->id.'.ics');
         $filename = 'listingics_'.$villas->id.'.ics';
         File::put($file_path,'test');
            $villasics = new VillaIcs;
            $villasics->villa_id = $villas->id;
            $villasics->ics_url = $request->ics_url;
            $villasics->file_name = $filename;
            $villasics->file_url = asset('icsfiles/'.$filename);
            $villasics->save();
      }
      return redirect()->back()->with('success', 'Villa has been updated');
   }
   public function addcustome(Request $request){
      print_r($request->all());
      $request->validate([
         'Customtitle' => 'required',
         'customedescription' => 'required',
      ]);
      $customedata = new CustomDetail;
      $customedata->title = $request->Customtitle;
      $customedata->villa_id = $request->villa_id;
      $customedata->description = $request->customedescription;
      $customedata->save();
      return redirect()->back()->with(['success'=>'successfully added custome field']);
   }
   public function cutomedelete(Request $request){
      $customedata = CustomDetail::find($request->id);
      $customedata->delete();
      return response()->json('successfully deleted data');
      
   }
   
  }
  

