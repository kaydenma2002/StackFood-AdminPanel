@extends('layouts.admin.app')

@section('title',translate('Subscription_Settings'))

@push('css_or_js')
@endpush

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">{{translate('messages.Subscription_Settings')}}</h1>
                <h5 class="d-flex flex-wrap justify-content-end">
                    <label class="switch--custom-label toggle-switch toggle-switch-sm d-inline-flex">
                        <span class="mr-2 switch--custom-label-text text-primary on text-uppercase">{{ translate('messages.on') }}</span>
                        <span class="mr-2 switch--custom-label-text off ">{{ translate('messages.Status') }}</span>
                        <input type="checkbox" id="data_status" class="toggle-switch-input" {{ data_get($free_trial_period,'status') == 1 ? 'checked':''}} >
                        <span class="toggle-switch-label text">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                </h5>
            </div>
        </div>
    </div>
    <!-- End Page Header -->
    <div class="row gx-2 gx-lg-3">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-body">

                    <?php
                    if(data_get($free_trial_period,'type') == 'year'){
                        $trial_period =isset($free_trial_period['data']) ? $free_trial_period['data']  / 365 : 0;
                    } else if(data_get($free_trial_period,'type') == 'month'){
                        $trial_period =isset($free_trial_period['data']) ? $free_trial_period['data']  / 30 : 0;
                    } else{
                        $trial_period =isset($free_trial_period['data']) ? $free_trial_period['data'] : 0 ;
                    }

                ?>
                    <form action="{{route('admin.subscription.settings_update')}}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-10">
                                <div class="form-group">
                                    <label class="input-label text--black" for="exampleFormControlInput1"> {{translate('messages.Free_trial_period')}} </label>
                                    <input type="text" value="{{ $trial_period }}" name="free_trial_period" class="form-control h--45px" placeholder="{{ translate('Ex:_90_Days') }}" min="1" max="999" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3)" step="1" required>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="form-group">
                                    <label class="input-label text--black mb-4" for="exampleFormControlInput1"></label>

                                    <select  name="type" class="custom-select form-control mt-2">
                                        <option  value="day" {{data_get($free_trial_period,'type')  == 'day'? 'selected' : '' }}>{{ translate('Day') }}</option>
                                        <option  value="month" {{data_get($free_trial_period,'type')  == 'month'? 'selected' : ''}}>{{ translate('Month') }}</option>
                                        <option  value="year" {{data_get($free_trial_period,'type')  == 'year'? 'selected' : ''}}>{{ translate('Year') }}</option>
                                    </select>
                                </div>

                                </div>
                        </div>
                        <div class="btn--container justify-content-end">
                            <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary">{{translate('messages.submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('script_2')
<script type="text/javascript">
    "use strict";
    let url = null;
    let status = null;
    $(document).ready(function () {
        $('body').on('change','#data_status', function(){
            if(this.checked){
                status = 1;
            }else{
                status = 0;
            }
            url = '{{ url('admin/subscription/settings/update') }}/'+status;
            $.ajax({
                url: url,
                method: 'get',
                success: function(result) {
                    if(result.status == 1){
                        toastr.success('{{ translate('messages.Free_trial_period_Enabled!') }}', {
                        CloseButton: true,
                        ProgressBar: true
                        });

                    } else{
                        toastr.success('{{ translate('messages.Free_trial_period_Disabled!') }}', {
                        CloseButton: true,
                        ProgressBar: true
                        });
                    }
                }
            });

        });
    });
</script>
@endpush
