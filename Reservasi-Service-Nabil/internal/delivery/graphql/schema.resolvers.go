package graphql

// THIS CODE IS A STARTING POINT ONLY. IT WILL NOT BE UPDATED WITH SCHEMA CHANGES.

import (
	"context"
	"reservasi/internal/domain"
)

type Resolver struct {
	BookingUsecase domain.BookingUsecase
}

// BookingSummary is the resolver for the bookingSummary field.
func (r *queryResolver) BookingSummary(ctx context.Context, id string) (*BookingSummary, error) {
	summary, err := r.BookingUsecase.GetSummary(id)
	if err != nil {
		return nil, err
	}

	// Map dari domain.BookingAddon ke graphql.BookingAddon
	var gqlAddons []*BookingAddon
	if summary.Booking.Addons != nil {
		for _, a := range summary.Booking.Addons {
			gqlAddons = append(gqlAddons, &BookingAddon{
				ID:             a.ID.String(),
				AddonID:        a.AddonID.String(),
				Quantity:       a.Quantity,
				PriceAtBooking: a.PriceAtBooking,
			})
		}
	}

	// Map dari domain ke graphql
	return &BookingSummary{
		Booking: &Booking{
			ID:               summary.Booking.ID.String(),
			GuestID:          summary.Booking.GuestID.String(),
			RoomID:           summary.Booking.RoomID.String(),
			CheckInDate:      summary.Booking.CheckInDate.Format("2006-01-02"),
			CheckOutDate:     summary.Booking.CheckOutDate.Format("2006-01-02"),
			TotalRoomPrice:   summary.Booking.TotalRoomPrice,
			TotalAddonsPrice: summary.Booking.TotalAddonsPrice,
			GrandTotal:       summary.Booking.GrandTotal,
			Status:           summary.Booking.Status,
			Addons:           gqlAddons,
		},
		RoomDetails: &Room{
			ID:            summary.RoomDetails.ID.String(),
			Name:          summary.RoomDetails.Name,
			Location:      summary.RoomDetails.Location,
			Description:   &summary.RoomDetails.Description,
			PricePerNight: summary.RoomDetails.PricePerNight,
			Status:        summary.RoomDetails.Status,
		},
		GuestDetails: &Guest{
			ID:          summary.GuestDetails.ID.String(),
			Name:        summary.GuestDetails.Name,
			Email:       summary.GuestDetails.Email,
			KtpNumber:   summary.GuestDetails.KtpNumber,
			PhoneNumber: &summary.GuestDetails.PhoneNumber,
		},
	}, nil
}

// Query returns QueryResolver implementation.
func (r *Resolver) Query() QueryResolver { return &queryResolver{r} }

type queryResolver struct{ *Resolver }
