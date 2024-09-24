@php
$module_type = 'settings';
$admin2 = DB::table('whatsapp_setting')->first();
$url = $admin2->url ?? '';
$instance_id = $admin2->instance_id ?? '';
$access_token = $admin2->access_token ?? '';
$message_type = $admin2->message_type ?? '';
$message_otp = $admin2->message_otp ?? '';
$message_new_order = $admin2->message_new_order ?? '';
$message_confirm = $admin2->message_order_confirm ?? '';
$message_cancel = $admin2->message_order_cancel ?? '';
$message_process = $admin2->message_order_processing ?? '';
$message_handover = $admin2->message_order_handover ?? '';
$message_pickup = $admin2->message_order_pickup ?? '';
$message_delivered = $admin2->message_order_delivered ?? '';
@endphp
@extends('layouts.admin.app')

@section('title', translate('messages.Whatsapp_Settings'))

@push('css_or_js')
@endpush

@section('content')
<div class="content container-fluid">
<!-- Page Header -->
<div class="page-header">
<h1 class="page-header-title mr-3">
<span class="page-header-icon">
<img src="{{ asset('public/assets/admin/img/business.png') }}" class="w--26" alt="">
</span>
<span>
{{translate('business_setup')}}
</span>
</h1>
@include('admin-views.business-settings.partials.nav-menu')
</div>
<!-- Page Header -->

<!-- End Page Header -->
<form action="{{ route('admin-views.business-settings.whatsapp-index') }}" method="post" enctype="multipart/form-data">
@csrf
<div class="row g-2">
<div class="col-lg-12">
<div class="card">
<div class="card-body">

<div class="row g-3">

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.URL') }}</label>
<input type="url" name="url" class="form-control" placeholder="Enter URL" value="{{ $url }}" required>
</div>

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Instance Id') }}</label>
<input type="text" name="instance_id" class="form-control" placeholder="Enter Instance Id" value="{{ $instance_id }}" required>
</div>

</div>

<div class="row g-3">

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Access Token') }}</label>
<input type="text" name="access_token" class="form-control" placeholder="Access Token" value="{{ $access_token }}" required>
</div>

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message Type') }}</label>
<select name="message_type" class="form-control h--45px" required>
<option value="">Select Type</option>
@if($message_type=='text')
<option value="text" selected>Text</option>
<option value="media">Media</option>
@elseif($message_type=='media')
<option value="text">Text</option>
<option value="media" selected>Media</option>
@else
<option value="text">Text</option>
<option value="media">Media</option>
@endif
</select>
</div>

</div>

<div class="row g-3">

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for OTP') }}</label>
<input name="message_otp" class="form-control" placeholder="Enter Message" value="{{ $message_otp }}" required>
</div>

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for New Order') }}</label>
<input name="message_new_order" class="form-control" placeholder="Enter Message" value="{{ $message_new_order}}" required>
</div>

</div>

<div class="row g-3">

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for Order Confirm') }}</label>
<input name="message_confirm" class="form-control" placeholder="Enter Message" value="{{ $message_confirm }}" required>
</div>

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for Order Cancel') }}</label>
<input name="message_cancel" class="form-control" placeholder="Enter Message" value="{{ $message_cancel}}" required>
</div>

</div>

<div class="row g-3">

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for Order Processing') }}</label>
<input name="message_process" class="form-control" placeholder="Enter Message" value="{{ $message_process }}" required>
</div>

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for Order Handover') }}</label>
<input name="message_handover" class="form-control" placeholder="Enter Message" value="{{ $message_handover}}" required>
</div>

</div>

<div class="row g-3">

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for Order Pickup') }}</label>
<input name="message_pickup" class="form-control" placeholder="Enter Message" value="{{ $message_pickup }}" required>
</div>

<div class="col-lg-6 col-sm-6">
<label class="form-label" for="exampleFormControlInput1">{{ translate('messages.Message for Order Delivered') }}</label>
<input name="message_delivered" class="form-control" placeholder="Enter Message" value="{{ $message_delivered}}" required>
</div>

</div>



<div class="btn--container justify-content-end">
<button type="reset" id="reset_btn" class="btn btn--reset">{{ translate('messages.reset') }}</button>
<button type="submit" id="submit" class="btn btn--primary">{{ translate('messages.save_information') }}</button>
</div>
</div>
</div>
</div>
</div>
</form>
</div>
@endsection
