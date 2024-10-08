@foreach($delivery_men as $key=>$dm)
<tr>
    <td>{{$key+1}}</td>
    <td>
        <a class="table-rest-info" href="{{route('admin.delivery-man.preview',[$dm['id']])}}">
            <img class="onerror-image" data-onerror-image="{{dynamicAsset('public/assets/admin/img/160x160/img1.jpg')}}"
                 src="{{ $dm['image_full_url'] }}"
                 alt="{{$dm['f_name']}} {{$dm['l_name']}}">
            <div class="info">
                <h5 class="text-hover-primary mb-0">{{$dm['f_name'].' '.$dm['l_name']}}</h5>
                <span class="d-block text-body">
                    <!-- Rating -->
                    <span class="rating">
                        <i class="tio-star"></i> {{count($dm->rating)>0?number_format($dm->rating[0]->average, 1, '.', ' '):0}}
                    </span>
                    <!-- Rating -->
                </span>
            </div>
        </a>
    </td>
    <td>
        <a class="deco-none" href="tel:{{$dm['phone']}}">{{$dm['phone']}}</a>
    </td>
    <td>
        @if($dm->zone)
        <span>{{$dm->zone->name}}</span>
        @else
        <span>{{translate('messages.zone_deleted')}}</span>
        @endif
    </td>
    <!-- Static Data -->
    <td class="text-center">
        <div class="pr-3">
            {{ $dm->orders ? count($dm->orders):0 }}
        </div>
    </td>
    <!-- Static Data -->
    <td>
        <div>
            <!-- Status -->
            {{ translate('Currenty_Assigned_Orders') }} : {{$dm->current_orders}}
            <!-- Status -->
        </div>
        @if($dm->application_status == 'approved')
            @if($dm->active)
            <div>
                {{ translate('Active_Status') }} : <strong class="text-primary text-capitalize">{{translate('messages.online')}}</strong>
            </div>
            @else
            <div>
                {{ translate('Active_Status') }} : <strong class="text-secondary text-capitalize">{{translate('messages.offline')}}</strong>
            </div>
            @endif
        @elseif ($dm->application_status == 'denied')
            <div>
                {{ translate('Active_Status') }} : <strong class="text-danger text-capitalize">{{translate('messages.denied')}}</strong>
            </div>
        @else
            <div>
                {{ translate('Active_Status') }} : <strong class="text-info text-capitalize">{{translate('messages.pending')}}</strong>
            </div>
        @endif
    </td>
    <td>
        <div class="btn--container justify-content-center">
            <a class="btn btn-sm btn--primary btn-outline-primary action-btn" href="{{route('admin.delivery-man.edit',[$dm['id']])}}" title="{{translate('messages.edit')}}"><i class="tio-edit"></i></a>
            <a class="btn btn-sm btn--danger btn-outline-danger action-btn form-alert" href="javascript:" data-id="delivery-man-{{$dm['id']}}" data-message="{{ translate('Want_to_remove_this_deliveryman_?') }}" title="{{translate('messages.delete')}}"><i class="tio-delete-outlined"></i>
            </a>
            <form action="{{route('admin.delivery-man.delete',[$dm['id']])}}" method="post" id="delivery-man-{{$dm['id']}}">
                @csrf @method('delete')
            </form>
        </div>
    </td>
</tr>
@endforeach
