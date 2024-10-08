@foreach($restaurants as $key=>$dm)
    <tr>
        <td>{{$key+1}}</td>
        <td>
            <a href="{{route('admin.restaurant.view', $dm->id)}}" alt="view restaurant" class="table-rest-info">
                <img class="onerror-image" data-onerror-image="{{dynamicAsset('public/assets/admin/img/100x100/food-default-image.png')}}"

                     src="{{ $dm['logo_full_url'] ?? dynamicAsset('public/assets/admin/img/100x100/food-default-image.png') }}">
                <div class="info">
                                                <span class="d-block text-body">
                                                    {{Str::limit($dm->name,20,'...')}}<br>
                                                    <!-- Rating -->
                                                    <span class="rating">
                                                        @if ($dm->reviews_count)
                                                            @php($reviews_count = $dm->reviews_count)
                                                            @php($reviews = 1)
                                                        @else
                                                            @php($reviews = 0)
                                                            @php($reviews_count = 1)
                                                        @endif
                                                    <i class="tio-star"></i> {{ round($dm->reviews_sum_rating /$reviews_count,1) }}
                                                </span>
                                                    <!-- Rating -->
                                                </span>
                </div>
            </a>
        </td>
        <td>
                                        <span class="d-block owner--name text-center">
                                            {{$dm->vendor->f_name.' '.$dm->vendor->l_name}}
                                        </span>
            <span class="d-block font-size-sm text-center">
                                            {{$dm['phone']}}
                                        </span>
        </td>
        <td>
            {{$dm->zone?$dm->zone->name:translate('messages.zone_deleted')}}
        </td>
        <td>
            <div class="white-space-initial">
                @if ($dm->cuisine)
                    @forelse($dm->cuisine as $c)
                        {{$c->name.','}}
                    @empty
                        {{ translate('Cuisine_not_found') }}
                    @endforelse
                @endif
            </div>
        </td>
        <td>
            @if(isset($dm->vendor->status))
                @if($dm->vendor->status)
                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$dm->id}}">
                        <input type="checkbox" data-url="{{route('admin.restaurant.status',[$dm->id,$dm->status?0:1])}}" data-message="{{translate('messages.you_want_to_change_this_restaurant_status')}}" class="toggle-switch-input status_change_alert" id="stocksCheckbox{{$dm->id}}" {{$dm->status?'checked':''}}>
                        <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                    </label>
                @else
                    <span class="badge badge-soft-danger">{{translate('messages.denied')}}</span>
                @endif
            @else
                <span class="badge badge-soft-danger">{{translate('messages.not_approved')}}</span>
            @endif
        </td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn btn-sm btn--primary btn-outline-primary action-btn"
                   href="{{route('admin.restaurant.edit',[$dm['id']])}}" title="{{translate('messages.edit_restaurant')}}"><i class="tio-edit"></i>
                </a>
                <a class="btn btn-sm btn--warning btn-outline-warning action-btn"
                   href="{{route('admin.restaurant.view',[$dm['id']])}}" title="{{translate('messages.view_restaurant')}}"><i class="tio-invisible"></i>
                </a>
            </div>
        </td>
    </tr>
@endforeach
